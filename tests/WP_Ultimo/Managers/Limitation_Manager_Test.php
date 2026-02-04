<?php
/**
 * Unit tests for Limitation_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Limitation_Manager;
use WP_Ultimo\Objects\Limitations;
use WP_Ultimo\Models\Product;
use WP_Ultimo\Models\Membership;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Models\Site;
use WP_Ultimo\Database\Sites\Site_Type;

class Limitation_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	/**
	 * Create a Customer model directly, bypassing wu_create_customer.
	 *
	 * This avoids collisions with the wu_customers table which is not
	 * rolled back between individual test methods.
	 *
	 * @return Customer
	 */
	protected function create_test_customer(): Customer {

		$user_id = self::factory()->user->create([
			'role' => 'subscriber',
		]);

		$customer = new Customer([
			'user_id' => $user_id,
		]);
		$customer->set_skip_validation(true);
		$customer->save();

		return $customer;
	}

	protected function get_manager_class(): string {
		return Limitation_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	/**
	 * Test get_all_plugins returns an array.
	 */
	public function test_get_all_plugins_returns_array(): void {

		$manager = $this->get_manager_instance();
		$plugins = $manager->get_all_plugins();

		$this->assertIsArray($plugins);
	}

	/**
	 * Test get_all_themes returns an array.
	 */
	public function test_get_all_themes_returns_array(): void {

		$manager = $this->get_manager_instance();
		$themes  = $manager->get_all_themes();

		$this->assertIsArray($themes);
	}

	/**
	 * Test get_object_type returns the correct type for a Product model.
	 */
	public function test_get_object_type_with_product(): void {

		$manager = $this->get_manager_instance();

		$product = new \WP_Ultimo\Models\Product();
		$type    = $manager->get_object_type($product);

		$this->assertEquals('product', $type);
	}

	/**
	 * Test get_object_type returns false for unknown objects.
	 */
	public function test_get_object_type_with_unknown(): void {

		$manager = $this->get_manager_instance();
		$type    = $manager->get_object_type(new \stdClass());

		$this->assertFalse($type);
	}

	// ---------------------------------------------------------------
	// get_object_type tests
	// ---------------------------------------------------------------

	/**
	 * Test get_object_type returns membership for Membership model.
	 */
	public function test_get_object_type_with_membership(): void {

		$manager = $this->get_manager_instance();

		$membership = new Membership();
		$type       = $manager->get_object_type($membership);

		$this->assertEquals('membership', $type);
	}

	/**
	 * Test get_object_type returns site for Site model.
	 */
	public function test_get_object_type_with_site(): void {

		$manager = $this->get_manager_instance();

		$blog_id = self::factory()->blog->create();
		$site    = new Site(['blog_id' => $blog_id]);

		$type = $manager->get_object_type($site);

		$this->assertEquals('site', $type);
	}

	/**
	 * Test get_object_type filter can modify the result.
	 */
	public function test_get_object_type_filter(): void {

		$manager = $this->get_manager_instance();

		add_filter('wu_limitations_get_object_type', function ($model, $object_model) {
			if ($object_model instanceof \stdClass) {
				return 'custom_type';
			}
			return $model;
		}, 10, 2);

		$type = $manager->get_object_type(new \stdClass());

		$this->assertEquals('custom_type', $type);

		remove_all_filters('wu_limitations_get_object_type');
	}

	// ---------------------------------------------------------------
	// Limitations object tests
	// ---------------------------------------------------------------

	/**
	 * Test Limitations repository returns the expected modules.
	 */
	public function test_limitations_repository_contains_expected_modules(): void {

		$repository = Limitations::repository();

		$expected_modules = [
			'post_types',
			'plugins',
			'sites',
			'themes',
			'visits',
			'disk_space',
			'users',
			'site_templates',
			'domain_mapping',
			'customer_user_role',
			'hide_credits',
		];

		foreach ($expected_modules as $module) {
			$this->assertArrayHasKey($module, $repository, "Repository should contain '{$module}' module.");
		}
	}

	/**
	 * Test Limitations::get_empty returns a valid Limitations object.
	 */
	public function test_get_empty_limitations(): void {

		$limitations = Limitations::get_empty();

		$this->assertInstanceOf(Limitations::class, $limitations);
	}

	/**
	 * Test Limitations with no data has_limitations returns false.
	 */
	public function test_empty_limitations_has_no_limitations(): void {

		$limitations = new Limitations([]);

		$this->assertFalse($limitations->has_limitations());
	}

	/**
	 * Test Limitations with enabled module has_limitations returns true.
	 */
	public function test_limitations_with_enabled_module_has_limitations(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$this->assertTrue($limitations->has_limitations());
	}

	/**
	 * Test Limitations exists method.
	 */
	public function test_limitations_exists_method(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$this->assertTrue($limitations->exists('sites'));
		$this->assertFalse($limitations->exists('nonexistent'));
	}

	/**
	 * Test Limitations is_module_enabled method.
	 */
	public function test_limitations_is_module_enabled(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
			'visits' => [
				'enabled' => false,
				'limit'   => 0,
			],
		]);

		$this->assertTrue($limitations->is_module_enabled('sites'));
		$this->assertFalse($limitations->is_module_enabled('visits'));
	}

	/**
	 * Test Limitations to_array returns original data.
	 */
	public function test_limitations_to_array(): void {

		$data = [
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		];

		$limitations = new Limitations($data);

		$this->assertEquals($data, $limitations->to_array());
	}

	// ---------------------------------------------------------------
	// Limit module access via magic getter
	// ---------------------------------------------------------------

	/**
	 * Test accessing sites limit module.
	 */
	public function test_sites_limit_module_access(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 3,
			],
		]);

		$sites = $limitations->sites;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Sites::class, $sites);
		$this->assertTrue($sites->is_enabled());
		$this->assertEquals(3, $sites->get_limit());
	}

	/**
	 * Test accessing disk_space limit module.
	 */
	public function test_disk_space_limit_module_access(): void {

		$limitations = new Limitations([
			'disk_space' => [
				'enabled' => true,
				'limit'   => 100,
			],
		]);

		$disk_space = $limitations->disk_space;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Disk_Space::class, $disk_space);
		$this->assertTrue($disk_space->is_enabled());
		$this->assertEquals(100, $disk_space->get_limit());
	}

	/**
	 * Test accessing visits limit module.
	 */
	public function test_visits_limit_module_access(): void {

		$limitations = new Limitations([
			'visits' => [
				'enabled' => true,
				'limit'   => 10000,
			],
		]);

		$visits = $limitations->visits;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Visits::class, $visits);
		$this->assertTrue($visits->is_enabled());
		$this->assertEquals(10000, $visits->get_limit());
	}

	/**
	 * Test accessing users limit module.
	 */
	public function test_users_limit_module_access(): void {

		$limitations = new Limitations([
			'users' => [
				'enabled' => true,
				'limit'   => [
					'administrator' => [
						'enabled' => true,
						'number'  => 1,
					],
					'editor' => [
						'enabled' => true,
						'number'  => 5,
					],
				],
			],
		]);

		$users = $limitations->users;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Users::class, $users);
		$this->assertTrue($users->is_enabled());
	}

	/**
	 * Test accessing plugins limit module.
	 */
	public function test_plugins_limit_module_access(): void {

		$limitations = new Limitations([
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'test-plugin/test-plugin.php' => [
						'visibility' => 'visible',
						'behavior'   => 'default',
					],
				],
			],
		]);

		$plugins = $limitations->plugins;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Plugins::class, $plugins);
		$this->assertTrue($plugins->is_enabled());
	}

	/**
	 * Test accessing themes limit module.
	 */
	public function test_themes_limit_module_access(): void {

		$limitations = new Limitations([
			'themes' => [
				'enabled' => true,
				'limit'   => [
					'twentytwentyfour' => [
						'visibility' => 'visible',
						'behavior'   => 'available',
					],
				],
			],
		]);

		$themes = $limitations->themes;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Themes::class, $themes);
		$this->assertTrue($themes->is_enabled());
	}

	/**
	 * Test accessing post_types limit module.
	 */
	public function test_post_types_limit_module_access(): void {

		$limitations = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [
					'post' => [
						'enabled' => true,
						'number'  => 100,
					],
					'page' => [
						'enabled' => true,
						'number'  => 50,
					],
				],
			],
		]);

		$post_types = $limitations->post_types;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Post_Types::class, $post_types);
		$this->assertTrue($post_types->is_enabled());
	}

	/**
	 * Test accessing domain_mapping limit module.
	 */
	public function test_domain_mapping_limit_module_access(): void {

		$limitations = new Limitations([
			'domain_mapping' => [
				'enabled' => true,
				'limit'   => null,
			],
		]);

		$domain_mapping = $limitations->domain_mapping;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Domain_Mapping::class, $domain_mapping);
		$this->assertTrue($domain_mapping->is_enabled());
	}

	/**
	 * Test accessing hide_credits limit module.
	 */
	public function test_hide_credits_limit_module_access(): void {

		$limitations = new Limitations([
			'hide_credits' => [
				'enabled' => true,
				'limit'   => true,
			],
		]);

		$hide_credits = $limitations->hide_credits;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Hide_Footer_Credits::class, $hide_credits);
		$this->assertTrue($hide_credits->is_enabled());
	}

	/**
	 * Test accessing customer_user_role limit module.
	 */
	public function test_customer_user_role_limit_module_access(): void {

		$limitations = new Limitations([
			'customer_user_role' => [
				'enabled' => true,
				'limit'   => 'administrator',
			],
		]);

		$customer_user_role = $limitations->customer_user_role;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Customer_User_Role::class, $customer_user_role);
		$this->assertTrue($customer_user_role->is_enabled());
	}

	/**
	 * Test accessing site_templates limit module.
	 */
	public function test_site_templates_limit_module_access(): void {

		$limitations = new Limitations([
			'site_templates' => [
				'enabled' => true,
				'limit'   => null,
				'mode'    => 'default',
			],
		]);

		$site_templates = $limitations->site_templates;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Site_Templates::class, $site_templates);
		$this->assertTrue($site_templates->is_enabled());
	}

	/**
	 * Test accessing a nonexistent module returns false.
	 */
	public function test_accessing_nonexistent_module_returns_false(): void {

		$limitations = new Limitations([]);

		$result = $limitations->nonexistent_module;

		$this->assertFalse($result);
	}

	// ---------------------------------------------------------------
	// Limit enabled/disabled tests
	// ---------------------------------------------------------------

	/**
	 * Test sites limit disabled by default when no data.
	 */
	public function test_sites_limit_enabled_by_default_when_no_enabled_key(): void {

		$limitations = new Limitations([
			'sites' => [],
		]);

		$sites = $limitations->sites;

		// Default enabled_default_value is true in base Limit
		$this->assertTrue($sites->is_enabled());
	}

	/**
	 * Test sites limit can be disabled.
	 */
	public function test_sites_limit_can_be_disabled(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => false,
				'limit'   => 5,
			],
		]);

		$this->assertFalse($limitations->sites->is_enabled());
	}

	/**
	 * Test hide_credits defaults to disabled.
	 */
	public function test_hide_credits_defaults_to_disabled(): void {

		$limitations = new Limitations([
			'hide_credits' => [],
		]);

		$hide_credits = $limitations->hide_credits;

		// Hide credits has enabled_default_value = false
		$this->assertFalse($hide_credits->is_enabled());
	}

	// ---------------------------------------------------------------
	// Limit has_own_limit and has_own_enabled tests
	// ---------------------------------------------------------------

	/**
	 * Test has_own_limit returns true when limit is set.
	 */
	public function test_has_own_limit_when_set(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 10,
			],
		]);

		$this->assertTrue($limitations->sites->has_own_limit());
	}

	/**
	 * Test has_own_limit returns false when limit is not set.
	 */
	public function test_has_own_limit_when_not_set(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
			],
		]);

		$this->assertFalse($limitations->sites->has_own_limit());
	}

	/**
	 * Test has_own_enabled returns true when enabled is set.
	 */
	public function test_has_own_enabled_when_set(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$this->assertTrue($limitations->sites->has_own_enabled());
	}

	/**
	 * Test has_own_enabled returns false when enabled is not set.
	 */
	public function test_has_own_enabled_when_not_set(): void {

		$limitations = new Limitations([
			'sites' => [
				'limit' => 5,
			],
		]);

		$this->assertFalse($limitations->sites->has_own_enabled());
	}

	// ---------------------------------------------------------------
	// Plugins limit tests
	// ---------------------------------------------------------------

	/**
	 * Test plugins is always enabled.
	 */
	public function test_plugins_limit_always_enabled(): void {

		$limitations = new Limitations([
			'plugins' => [
				'enabled' => false,
				'limit'   => [],
			],
		]);

		// Limit_Plugins::is_enabled() always returns true
		$this->assertTrue($limitations->plugins->is_enabled());
	}

	/**
	 * Test plugins default permissions.
	 */
	public function test_plugins_default_permissions(): void {

		$limitations = new Limitations([
			'plugins' => [
				'enabled' => true,
				'limit'   => [],
			],
		]);

		$plugin = $limitations->plugins->{'nonexistent-plugin/nonexistent.php'};

		$this->assertEquals('visible', $plugin->visibility);
		$this->assertEquals('default', $plugin->behavior);
	}

	/**
	 * Test plugins with custom permissions.
	 */
	public function test_plugins_custom_permissions(): void {

		$limitations = new Limitations([
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'akismet/akismet.php' => [
						'visibility' => 'hidden',
						'behavior'   => 'force_active',
					],
				],
			],
		]);

		$plugin = $limitations->plugins->{'akismet/akismet.php'};

		$this->assertEquals('hidden', $plugin->visibility);
		$this->assertEquals('force_active', $plugin->behavior);
	}

	/**
	 * Test plugins exists method.
	 */
	public function test_plugins_exists(): void {

		$limitations = new Limitations([
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'akismet/akismet.php' => [
						'visibility' => 'hidden',
						'behavior'   => 'force_active',
					],
				],
			],
		]);

		$this->assertTrue($limitations->plugins->exists('akismet/akismet.php'));
		$this->assertFalse($limitations->plugins->exists('nonexistent/nonexistent.php'));
	}

	/**
	 * Test plugins check method with various types.
	 */
	public function test_plugins_check_method(): void {

		$limitations = new Limitations([
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'akismet/akismet.php' => [
						'visibility' => 'visible',
						'behavior'   => 'force_active',
					],
				],
			],
		]);

		$this->assertTrue($limitations->plugins->allowed('akismet/akismet.php', 'visible'));
		$this->assertFalse($limitations->plugins->allowed('akismet/akismet.php', 'hidden'));
		$this->assertTrue($limitations->plugins->allowed('akismet/akismet.php', 'force_active'));
		$this->assertFalse($limitations->plugins->allowed('akismet/akismet.php', 'force_inactive'));
		$this->assertFalse($limitations->plugins->allowed('akismet/akismet.php', 'default'));
	}

	// ---------------------------------------------------------------
	// Themes limit tests
	// ---------------------------------------------------------------

	/**
	 * Test themes is always enabled.
	 */
	public function test_themes_limit_always_enabled(): void {

		$limitations = new Limitations([
			'themes' => [
				'enabled' => false,
				'limit'   => [],
			],
		]);

		// Limit_Themes::is_enabled() always returns true
		$this->assertTrue($limitations->themes->is_enabled());
	}

	/**
	 * Test themes default permissions.
	 */
	public function test_themes_default_permissions(): void {

		$limitations = new Limitations([
			'themes' => [
				'enabled' => true,
				'limit'   => [],
			],
		]);

		$theme = $limitations->themes->{'nonexistent-theme'};

		$this->assertEquals('visible', $theme->visibility);
		$this->assertEquals('available', $theme->behavior);
	}

	/**
	 * Test themes custom permissions.
	 */
	public function test_themes_custom_permissions(): void {

		$limitations = new Limitations([
			'themes' => [
				'enabled' => true,
				'limit'   => [
					'twentytwentyfour' => [
						'visibility' => 'hidden',
						'behavior'   => 'not_available',
					],
				],
			],
		]);

		$theme = $limitations->themes->twentytwentyfour;

		$this->assertEquals('hidden', $theme->visibility);
		$this->assertEquals('not_available', $theme->behavior);
	}

	/**
	 * Test themes get_forced_active_theme.
	 */
	public function test_themes_get_forced_active_theme(): void {

		$limitations = new Limitations([
			'themes' => [
				'enabled' => true,
				'limit'   => [
					'twentytwentyfour' => [
						'visibility' => 'visible',
						'behavior'   => 'force_active',
					],
					'twentytwentythree' => [
						'visibility' => 'visible',
						'behavior'   => 'available',
					],
				],
			],
		]);

		$forced = $limitations->themes->get_forced_active_theme();

		$this->assertEquals('twentytwentyfour', $forced);
	}

	/**
	 * Test themes get_forced_active_theme returns false when none forced.
	 */
	public function test_themes_get_forced_active_theme_returns_false_when_none(): void {

		$limitations = new Limitations([
			'themes' => [
				'enabled' => true,
				'limit'   => [
					'twentytwentyfour' => [
						'visibility' => 'visible',
						'behavior'   => 'available',
					],
				],
			],
		]);

		$forced = $limitations->themes->get_forced_active_theme();

		$this->assertFalse($forced);
	}

	/**
	 * Test themes get_available_themes.
	 */
	public function test_themes_get_available_themes(): void {

		$limitations = new Limitations([
			'themes' => [
				'enabled' => true,
				'limit'   => [
					'twentytwentyfour' => [
						'visibility' => 'visible',
						'behavior'   => 'available',
					],
					'twentytwentythree' => [
						'visibility' => 'visible',
						'behavior'   => 'not_available',
					],
					'twentytwentytwo' => [
						'visibility' => 'visible',
						'behavior'   => 'available',
					],
				],
			],
		]);

		$available = $limitations->themes->get_available_themes();

		$this->assertContains('twentytwentyfour', $available);
		$this->assertContains('twentytwentytwo', $available);
		$this->assertNotContains('twentytwentythree', $available);
	}

	/**
	 * Test themes exists method.
	 */
	public function test_themes_exists(): void {

		$limitations = new Limitations([
			'themes' => [
				'enabled' => true,
				'limit'   => [
					'twentytwentyfour' => [
						'visibility' => 'visible',
						'behavior'   => 'available',
					],
				],
			],
		]);

		$this->assertTrue($limitations->themes->exists('twentytwentyfour'));
		$this->assertFalse($limitations->themes->exists('nonexistent'));
	}

	/**
	 * Test themes get_all_themes.
	 */
	public function test_themes_get_all_themes(): void {

		$limitations = new Limitations([
			'themes' => [
				'enabled' => true,
				'limit'   => [
					'twentytwentyfour'  => [
						'visibility' => 'visible',
						'behavior'   => 'available',
					],
					'twentytwentythree' => [
						'visibility' => 'visible',
						'behavior'   => 'not_available',
					],
				],
			],
		]);

		$all_themes = $limitations->themes->get_all_themes();

		$this->assertCount(2, $all_themes);
		$this->assertContains('twentytwentyfour', $all_themes);
		$this->assertContains('twentytwentythree', $all_themes);
	}

	// ---------------------------------------------------------------
	// Post types limit tests
	// ---------------------------------------------------------------

	/**
	 * Test post_types default permissions for unknown types.
	 */
	public function test_post_types_default_permissions(): void {

		$limitations = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [],
			],
		]);

		$post_type = $limitations->post_types->unknown_type;

		$this->assertTrue($post_type->enabled);
		$this->assertEquals('', $post_type->number);
	}

	/**
	 * Test post_types check method with subtype.
	 */
	public function test_post_types_check_allowed(): void {

		$limitations = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [
					'post' => [
						'enabled' => true,
						'number'  => 10,
					],
				],
			],
		]);

		// Current count 5 is less than limit 10
		$this->assertTrue($limitations->post_types->allowed(5, 'post'));

		// Current count 10 is not less than limit 10
		$this->assertFalse($limitations->post_types->allowed(10, 'post'));

		// Current count 15 is not less than limit 10
		$this->assertFalse($limitations->post_types->allowed(15, 'post'));
	}

	/**
	 * Test post_types check with unlimited (0 or empty number).
	 */
	public function test_post_types_check_unlimited(): void {

		$limitations = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [
					'post' => [
						'enabled' => true,
						'number'  => 0,
					],
				],
			],
		]);

		// Number 0 means unlimited
		$this->assertTrue($limitations->post_types->allowed(9999, 'post'));
	}

	/**
	 * Test post_types check with disabled post type.
	 */
	public function test_post_types_check_disabled_type(): void {

		$limitations = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [
					'post' => [
						'enabled' => false,
						'number'  => 100,
					],
				],
			],
		]);

		// Post type is disabled
		$this->assertFalse($limitations->post_types->allowed(0, 'post'));
	}

	/**
	 * Test post_types check without type returns false.
	 */
	public function test_post_types_check_without_type(): void {

		$limitations = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [
					'post' => [
						'enabled' => true,
						'number'  => 10,
					],
				],
			],
		]);

		// No type provided
		$this->assertFalse($limitations->post_types->allowed(5, ''));
	}

	/**
	 * Test post_types exists method.
	 */
	public function test_post_types_exists(): void {

		$limitations = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [
					'post' => [
						'enabled' => true,
						'number'  => 10,
					],
				],
			],
		]);

		$this->assertTrue($limitations->post_types->exists('post'));
		$this->assertFalse($limitations->post_types->exists('nonexistent'));
	}

	// ---------------------------------------------------------------
	// Users limit tests
	// ---------------------------------------------------------------

	/**
	 * Test users limit check with subtype.
	 */
	public function test_users_check_allowed(): void {

		$limitations = new Limitations([
			'users' => [
				'enabled' => true,
				'limit'   => [
					'administrator' => [
						'enabled' => true,
						'number'  => 2,
					],
				],
			],
		]);

		// Current count 1 is less than limit 2
		$this->assertTrue($limitations->users->allowed(1, 'administrator'));

		// Current count 2 is not less than limit 2
		$this->assertFalse($limitations->users->allowed(2, 'administrator'));
	}

	/**
	 * Test users limit check with unlimited users.
	 */
	public function test_users_check_unlimited(): void {

		$limitations = new Limitations([
			'users' => [
				'enabled' => true,
				'limit'   => [
					'editor' => [
						'enabled' => true,
						'number'  => 0,
					],
				],
			],
		]);

		// Number 0 means unlimited
		$this->assertTrue($limitations->users->allowed(999, 'editor'));
	}

	/**
	 * Test users limit with disabled role.
	 */
	public function test_users_disabled_role(): void {

		$limitations = new Limitations([
			'users' => [
				'enabled' => true,
				'limit'   => [
					'subscriber' => [
						'enabled' => false,
						'number'  => 10,
					],
				],
			],
		]);

		$this->assertFalse($limitations->users->allowed(0, 'subscriber'));
	}

	/**
	 * Test users default permissions for unknown roles.
	 */
	public function test_users_default_permissions(): void {

		$limitations = new Limitations([
			'users' => [
				'enabled' => true,
				'limit'   => [],
			],
		]);

		$role = $limitations->users->unknown_role;

		$this->assertTrue($role->enabled);
		$this->assertEquals('', $role->number);
	}

	// ---------------------------------------------------------------
	// Sites limit tests
	// ---------------------------------------------------------------

	/**
	 * Test sites limit get_limit returns correct value.
	 */
	public function test_sites_limit_get_limit(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$this->assertEquals(5, $limitations->sites->get_limit());
	}

	/**
	 * Test sites limit check always returns true.
	 */
	public function test_sites_limit_check_returns_true(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 1,
			],
		]);

		// Limit_Sites::check() always returns true
		$this->assertTrue($limitations->sites->allowed(100));
	}

	// ---------------------------------------------------------------
	// Disk space limit tests
	// ---------------------------------------------------------------

	/**
	 * Test disk_space get_limit.
	 */
	public function test_disk_space_get_limit(): void {

		$limitations = new Limitations([
			'disk_space' => [
				'enabled' => true,
				'limit'   => 500,
			],
		]);

		$this->assertEquals(500, $limitations->disk_space->get_limit());
	}

	/**
	 * Test disk_space check always returns true.
	 */
	public function test_disk_space_check_returns_true(): void {

		$limitations = new Limitations([
			'disk_space' => [
				'enabled' => true,
				'limit'   => 100,
			],
		]);

		$this->assertTrue($limitations->disk_space->allowed(999));
	}

	// ---------------------------------------------------------------
	// Visits limit tests
	// ---------------------------------------------------------------

	/**
	 * Test visits limit get_limit.
	 */
	public function test_visits_limit_get_limit(): void {

		$limitations = new Limitations([
			'visits' => [
				'enabled' => true,
				'limit'   => 50000,
			],
		]);

		$this->assertEquals(50000, $limitations->visits->get_limit());
	}

	// ---------------------------------------------------------------
	// Domain mapping limit tests
	// ---------------------------------------------------------------

	/**
	 * Test domain_mapping enabled state.
	 */
	public function test_domain_mapping_enabled(): void {

		$limitations = new Limitations([
			'domain_mapping' => [
				'enabled' => true,
				'limit'   => null,
			],
		]);

		$this->assertTrue($limitations->domain_mapping->is_enabled());
	}

	/**
	 * Test domain_mapping disabled state.
	 */
	public function test_domain_mapping_disabled(): void {

		$limitations = new Limitations([
			'domain_mapping' => [
				'enabled' => false,
				'limit'   => null,
			],
		]);

		$this->assertFalse($limitations->domain_mapping->is_enabled());
	}

	/**
	 * Test domain_mapping get_mode.
	 */
	public function test_domain_mapping_get_mode(): void {

		$limitations = new Limitations([
			'domain_mapping' => [
				'enabled' => true,
				'limit'   => null,
				'mode'    => 'assign_template',
			],
		]);

		$this->assertEquals('assign_template', $limitations->domain_mapping->get_mode());
	}

	/**
	 * Test domain_mapping default mode.
	 */
	public function test_domain_mapping_default_mode(): void {

		$limitations = new Limitations([
			'domain_mapping' => [
				'enabled' => true,
				'limit'   => null,
			],
		]);

		$this->assertEquals('default', $limitations->domain_mapping->get_mode());
	}

	// ---------------------------------------------------------------
	// Hide footer credits limit tests
	// ---------------------------------------------------------------

	/**
	 * Test hide_credits check when enabled with boolean true limit.
	 */
	public function test_hide_credits_check_enabled_with_true_limit(): void {

		$limitations = new Limitations([
			'hide_credits' => [
				'enabled' => true,
				'limit'   => true,
			],
		]);

		$this->assertTrue($limitations->hide_credits->allowed(null));
	}

	/**
	 * Test hide_credits check when disabled.
	 */
	public function test_hide_credits_check_when_disabled(): void {

		$limitations = new Limitations([
			'hide_credits' => [
				'enabled' => false,
				'limit'   => true,
			],
		]);

		$this->assertFalse($limitations->hide_credits->allowed(null));
	}

	// ---------------------------------------------------------------
	// Customer user role limit tests
	// ---------------------------------------------------------------

	/**
	 * Test customer_user_role get_limit with specific role.
	 */
	public function test_customer_user_role_get_limit_specific(): void {

		$limitations = new Limitations([
			'customer_user_role' => [
				'enabled' => true,
				'limit'   => 'editor',
			],
		]);

		$this->assertEquals('editor', $limitations->customer_user_role->get_limit());
	}

	// ---------------------------------------------------------------
	// Site templates limit tests
	// ---------------------------------------------------------------

	/**
	 * Test site_templates get_mode.
	 */
	public function test_site_templates_get_mode(): void {

		$limitations = new Limitations([
			'site_templates' => [
				'enabled' => true,
				'limit'   => null,
				'mode'    => 'assign_template',
			],
		]);

		$this->assertEquals('assign_template', $limitations->site_templates->get_mode());
	}

	/**
	 * Test site_templates default mode.
	 */
	public function test_site_templates_default_mode(): void {

		$limitations = new Limitations([
			'site_templates' => [
				'enabled' => true,
				'limit'   => null,
			],
		]);

		$this->assertEquals('default', $limitations->site_templates->get_mode());
	}

	/**
	 * Test site_templates get_available_site_templates.
	 */
	public function test_site_templates_get_available(): void {

		$limitations = new Limitations([
			'site_templates' => [
				'enabled' => true,
				'limit'   => [
					'1' => [
						'behavior' => 'available',
					],
					'2' => [
						'behavior' => 'not_available',
					],
					'3' => [
						'behavior' => 'pre_selected',
					],
				],
				'mode' => 'choose_available_templates',
			],
		]);

		$available = $limitations->site_templates->get_available_site_templates();

		$this->assertContains(1, $available);
		$this->assertNotContains(2, $available);
		$this->assertContains(3, $available);
	}

	/**
	 * Test site_templates get_pre_selected_site_template.
	 */
	public function test_site_templates_get_pre_selected(): void {

		$limitations = new Limitations([
			'site_templates' => [
				'enabled' => true,
				'limit'   => [
					'1' => [
						'behavior' => 'available',
					],
					'2' => [
						'behavior' => 'pre_selected',
					],
				],
				'mode' => 'choose_available_templates',
			],
		]);

		$pre_selected = $limitations->site_templates->get_pre_selected_site_template();

		$this->assertEquals('2', $pre_selected);
	}

	/**
	 * Test site_templates get_pre_selected_site_template returns false when none.
	 */
	public function test_site_templates_get_pre_selected_returns_false(): void {

		$limitations = new Limitations([
			'site_templates' => [
				'enabled' => true,
				'limit'   => [
					'1' => [
						'behavior' => 'available',
					],
				],
				'mode' => 'choose_available_templates',
			],
		]);

		$pre_selected = $limitations->site_templates->get_pre_selected_site_template();

		$this->assertFalse($pre_selected);
	}

	// ---------------------------------------------------------------
	// Limitations merge tests
	// ---------------------------------------------------------------

	/**
	 * Test merging two limitations with summing (default).
	 */
	public function test_merge_limitations_sum_numeric_values(): void {

		$base = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$addon = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 3,
			],
		]);

		$merged = $base->merge($addon);

		$this->assertEquals(8, $merged->sites->get_limit());
	}

	/**
	 * Test merging limitations with override (true).
	 */
	public function test_merge_limitations_override(): void {

		$base = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$addon = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 3,
			],
		]);

		$merged = $base->merge(true, $addon);

		$this->assertEquals(3, $merged->sites->get_limit());
	}

	/**
	 * Test merging limitations preserves unlimited values when summing.
	 */
	public function test_merge_limitations_preserves_unlimited(): void {

		$base = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 0, // unlimited
			],
		]);

		$addon = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$merged = $base->merge($addon);

		// When one value is 0 (unlimited) and we're summing, unlimited should be preserved
		$this->assertEquals(0, $merged->sites->get_limit());
	}

	/**
	 * Test merging disk space limitations.
	 */
	public function test_merge_disk_space_limitations(): void {

		$base = new Limitations([
			'disk_space' => [
				'enabled' => true,
				'limit'   => 100,
			],
		]);

		$addon = new Limitations([
			'disk_space' => [
				'enabled' => true,
				'limit'   => 50,
			],
		]);

		$merged = $base->merge($addon);

		$this->assertEquals(150, $merged->disk_space->get_limit());
	}

	/**
	 * Test merging visits limitations.
	 */
	public function test_merge_visits_limitations(): void {

		$base = new Limitations([
			'visits' => [
				'enabled' => true,
				'limit'   => 10000,
			],
		]);

		$addon = new Limitations([
			'visits' => [
				'enabled' => true,
				'limit'   => 5000,
			],
		]);

		$merged = $base->merge($addon);

		$this->assertEquals(15000, $merged->visits->get_limit());
	}

	/**
	 * Test merging with disabled module in second set is skipped when summing.
	 */
	public function test_merge_skips_disabled_module_when_summing(): void {

		$base = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$disabled = new Limitations([
			'sites' => [
				'enabled' => false,
				'limit'   => 3,
			],
		]);

		$merged = $base->merge($disabled);

		// Disabled module should be skipped when summing
		$this->assertEquals(5, $merged->sites->get_limit());
	}

	/**
	 * Test merging multiple limitations.
	 */
	public function test_merge_multiple_limitations(): void {

		$base = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 2,
			],
		]);

		$addon1 = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 3,
			],
		]);

		$addon2 = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 1,
			],
		]);

		$merged = $base->merge($addon1, $addon2);

		$this->assertEquals(6, $merged->sites->get_limit());
	}

	/**
	 * Test merging with empty limitations.
	 */
	public function test_merge_with_empty_limitations(): void {

		$base = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$empty = new Limitations([]);

		$merged = $base->merge($empty);

		$this->assertEquals(5, $merged->sites->get_limit());
	}

	/**
	 * Test merging with non-array value is skipped.
	 */
	public function test_merge_with_non_array_value_skipped(): void {

		$base = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$merged = $base->merge('not-an-array');

		$this->assertEquals(5, $merged->sites->get_limit());
	}

	/**
	 * Test merging plugin behavior priorities.
	 */
	public function test_merge_plugin_behavior_priorities(): void {

		$base_data = [
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'akismet/akismet.php' => [
						'id'         => 'plugins',
						'visibility' => 'visible',
						'behavior'   => 'default',
					],
				],
			],
		];

		$override_data = [
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'akismet/akismet.php' => [
						'id'         => 'plugins',
						'visibility' => 'visible',
						'behavior'   => 'force_active',
					],
				],
			],
		];

		$base    = new Limitations($base_data);
		$merged  = $base->merge($override_data);

		// force_active has higher priority than default, so it should win
		$plugin = $merged->plugins->{'akismet/akismet.php'};
		$this->assertEquals('force_active', $plugin->behavior);
	}

	/**
	 * Test merging theme behavior priorities.
	 */
	public function test_merge_theme_behavior_priorities(): void {

		$base_data = [
			'themes' => [
				'enabled' => true,
				'limit'   => [
					'twentytwentyfour' => [
						'id'         => 'themes',
						'visibility' => 'visible',
						'behavior'   => 'not_available',
					],
				],
			],
		];

		$override_data = [
			'themes' => [
				'enabled' => true,
				'limit'   => [
					'twentytwentyfour' => [
						'id'         => 'themes',
						'visibility' => 'visible',
						'behavior'   => 'available',
					],
				],
			],
		];

		$base   = new Limitations($base_data);
		$merged = $base->merge($override_data);

		$theme = $merged->themes->twentytwentyfour;
		$this->assertEquals('available', $theme->behavior);
	}

	/**
	 * Test merging visibility priorities.
	 */
	public function test_merge_visibility_priorities(): void {

		$base_data = [
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'akismet/akismet.php' => [
						'id'         => 'plugins',
						'visibility' => 'hidden',
						'behavior'   => 'default',
					],
				],
			],
		];

		$override_data = [
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'akismet/akismet.php' => [
						'id'         => 'plugins',
						'visibility' => 'visible',
						'behavior'   => 'default',
					],
				],
			],
		];

		$base   = new Limitations($base_data);
		$merged = $base->merge($override_data);

		$plugin = $merged->plugins->{'akismet/akismet.php'};
		// visible has higher priority than hidden
		$this->assertEquals('visible', $plugin->visibility);
	}

	// ---------------------------------------------------------------
	// Serialization tests
	// ---------------------------------------------------------------

	/**
	 * Test Limitations serialization round-trip.
	 */
	public function test_limitations_serialization(): void {

		$data = [
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
			'disk_space' => [
				'enabled' => true,
				'limit'   => 100,
			],
		];

		$limitations = new Limitations($data);

		$serialized   = serialize($limitations);
		$unserialized = unserialize($serialized);

		$this->assertInstanceOf(Limitations::class, $unserialized);
		$this->assertEquals($data, $unserialized->to_array());
	}

	/**
	 * Test Limit module serialization.
	 */
	public function test_limit_module_json_serialization(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$json = $limitations->sites->jsonSerialize();

		$this->assertIsString($json);

		$decoded = json_decode($json, true);

		$this->assertIsArray($decoded);
		$this->assertTrue($decoded['enabled']);
		$this->assertEquals(5, $decoded['limit']);
	}

	/**
	 * Test Limit module to_array.
	 */
	public function test_limit_module_to_array(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$array = $limitations->sites->to_array();

		$this->assertIsArray($array);
		$this->assertArrayHasKey('enabled', $array);
		$this->assertArrayHasKey('limit', $array);
		$this->assertArrayHasKey('id', $array);
		$this->assertEquals('sites', $array['id']);
	}

	// ---------------------------------------------------------------
	// Limit default_state tests
	// ---------------------------------------------------------------

	/**
	 * Test base Limit default_state.
	 */
	public function test_limit_default_state(): void {

		$state = \WP_Ultimo\Limitations\Limit_Sites::default_state();

		$this->assertArrayHasKey('enabled', $state);
		$this->assertArrayHasKey('limit', $state);
		$this->assertFalse($state['enabled']);
		$this->assertNull($state['limit']);
	}

	/**
	 * Test site_templates default_state includes mode.
	 */
	public function test_site_templates_default_state(): void {

		$state = \WP_Ultimo\Limitations\Limit_Site_Templates::default_state();

		$this->assertArrayHasKey('mode', $state);
		$this->assertEquals('default', $state['mode']);
	}

	/**
	 * Test domain_mapping default_state.
	 */
	public function test_domain_mapping_default_state(): void {

		$state = \WP_Ultimo\Limitations\Limit_Domain_Mapping::default_state();

		$this->assertArrayHasKey('mode', $state);
		$this->assertEquals('default', $state['mode']);
		$this->assertTrue($state['enabled']);
	}

	/**
	 * Test hide_credits default_state.
	 */
	public function test_hide_credits_default_state(): void {

		$state = \WP_Ultimo\Limitations\Limit_Hide_Footer_Credits::default_state();

		$this->assertFalse($state['enabled']);
		$this->assertFalse($state['limit']);
	}

	/**
	 * Test customer_user_role default_state.
	 */
	public function test_customer_user_role_default_state(): void {

		$state = \WP_Ultimo\Limitations\Limit_Customer_User_Role::default_state();

		$this->assertTrue($state['enabled']);
		$this->assertEquals('default', $state['limit']);
	}

	// ---------------------------------------------------------------
	// Limit get_id tests
	// ---------------------------------------------------------------

	/**
	 * Test get_id for each limit module.
	 */
	public function test_limit_module_ids(): void {

		$modules_to_test = [
			'sites'              => 'sites',
			'disk_space'         => 'disk_space',
			'visits'             => 'visits',
			'users'              => 'users',
			'post_types'         => 'post_types',
			'plugins'            => 'plugins',
			'themes'             => 'themes',
			'domain_mapping'     => 'domain_mapping',
			'site_templates'     => 'site_templates',
			'customer_user_role' => 'customer_user_role',
			'hide_credits'       => 'hide_credits',
		];

		foreach ($modules_to_test as $module_key => $expected_id) {
			$limitations = new Limitations([
				$module_key => [
					'enabled' => true,
					'limit'   => null,
				],
			]);

			$module = $limitations->{$module_key};
			$this->assertEquals($expected_id, $module->get_id(), "Module '$module_key' should have id '$expected_id'.");
		}
	}

	// ---------------------------------------------------------------
	// Limitation_Manager plugin exclusion tests
	// ---------------------------------------------------------------

	/**
	 * Test get_all_plugins excludes WP Ultimo plugin.
	 */
	public function test_get_all_plugins_excludes_wp_ultimo(): void {

		$manager = $this->get_manager_instance();
		$plugins = $manager->get_all_plugins();

		$this->assertArrayNotHasKey('wp-ultimo/wp-ultimo.php', $plugins);
	}

	/**
	 * Test get_all_plugins excludes user-switching plugin.
	 */
	public function test_get_all_plugins_excludes_user_switching(): void {

		$manager = $this->get_manager_instance();
		$plugins = $manager->get_all_plugins();

		$this->assertArrayNotHasKey('user-switching/user-switching.php', $plugins);
	}

	/**
	 * Test plugin exclusion list filter.
	 */
	public function test_plugin_exclusion_list_filter(): void {

		add_filter('wu_limitations_plugin_exclusion_list', function ($list) {
			$list[] = 'custom-plugin/custom-plugin.php';
			return $list;
		});

		$manager = $this->get_manager_instance();
		$plugins = $manager->get_all_plugins();

		$this->assertArrayNotHasKey('custom-plugin/custom-plugin.php', $plugins);

		remove_all_filters('wu_limitations_plugin_exclusion_list');
	}

	/**
	 * Test theme exclusion list filter.
	 */
	public function test_theme_exclusion_list_filter(): void {

		$manager = $this->get_manager_instance();

		// First get all themes count
		$all_themes = $manager->get_all_themes();
		$count      = count($all_themes);

		if ($count > 0) {
			$first_key = array_key_first($all_themes);

			add_filter('wu_limitations_theme_exclusion_list', function ($list) use ($first_key) {
				$list[] = $first_key;
				return $list;
			});

			$filtered_themes = $manager->get_all_themes();

			$this->assertArrayNotHasKey($first_key, $filtered_themes);
			$this->assertCount($count - 1, $filtered_themes);

			remove_all_filters('wu_limitations_theme_exclusion_list');
		} else {
			$this->assertCount(0, $all_themes);
		}
	}

	// ---------------------------------------------------------------
	// add_limitation_sections tests
	// ---------------------------------------------------------------

	/**
	 * Test add_limitation_sections returns array for product.
	 */
	public function test_add_limitation_sections_for_product(): void {

		$manager = $this->get_manager_instance();

		$product = new Product([
			'name' => 'Test Plan',
			'slug' => 'test-plan-sections',
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $product);

		$this->assertIsArray($sections);

		// Check that expected sections exist
		$this->assertArrayHasKey('sites', $sections);
		$this->assertArrayHasKey('users', $sections);
		$this->assertArrayHasKey('post_types', $sections);
		$this->assertArrayHasKey('limit_disk_space', $sections);
		$this->assertArrayHasKey('custom_domain', $sections);
		$this->assertArrayHasKey('hide_credits', $sections);
		$this->assertArrayHasKey('allowed_themes', $sections);
		$this->assertArrayHasKey('allowed_plugins', $sections);
		$this->assertArrayHasKey('reset_limitations', $sections);
	}

	/**
	 * Test add_limitation_sections for membership.
	 */
	public function test_add_limitation_sections_for_membership(): void {

		$manager  = $this->get_manager_instance();
		$customer = $this->create_test_customer();

		$membership = new Membership([
			'customer_id' => $customer->get_id(),
			'status'      => 'active',
		]);
		$membership->set_skip_validation(true);
		$membership->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $membership);

		$this->assertIsArray($sections);
		$this->assertArrayHasKey('sites', $sections);
		$this->assertArrayHasKey('users', $sections);
		$this->assertArrayHasKey('post_types', $sections);
		$this->assertArrayHasKey('limit_disk_space', $sections);
		$this->assertArrayHasKey('reset_limitations', $sections);
	}

	/**
	 * Test add_limitation_sections for non-customer-owned site.
	 */
	public function test_add_limitation_sections_for_non_customer_site(): void {

		$manager = $this->get_manager_instance();

		$blog_id = self::factory()->blog->create();
		$site    = new Site([
			'blog_id' => $blog_id,
			'type'    => Site_Type::MAIN,
		]);

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $site);

		$this->assertIsArray($sections);
		// For non-customer-owned sites, only a 'sites' section with a note should exist
		$this->assertArrayHasKey('sites', $sections);
		$this->assertArrayHasKey('fields', $sections['sites']);
		$this->assertArrayHasKey('note', $sections['sites']['fields']);
	}

	/**
	 * Test add_limitation_sections for customer-owned site does not have sites section.
	 */
	public function test_add_limitation_sections_for_customer_owned_site_no_sites(): void {

		$manager = $this->get_manager_instance();

		$blog_id = self::factory()->blog->create();
		$site    = new Site([
			'blog_id' => $blog_id,
			'type'    => Site_Type::CUSTOMER_OWNED,
		]);

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $site);

		// For customer-owned sites, it should NOT have a 'sites' tab (that's only for product/membership)
		// But it should have other limitation sections
		$this->assertArrayHasKey('users', $sections);
		$this->assertArrayHasKey('post_types', $sections);
		$this->assertArrayHasKey('limit_disk_space', $sections);
	}

	// ---------------------------------------------------------------
	// register_user_fields tests
	// ---------------------------------------------------------------

	/**
	 * Test register_user_fields adds fields for each user role.
	 */
	public function test_register_user_fields_adds_role_fields(): void {

		$manager = $this->get_manager_instance();

		$product = new Product([
			'name' => 'Test Plan User Fields',
			'slug' => 'test-plan-user-fields',
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [
			'users' => [
				'title'  => 'Users',
				'fields' => [],
			],
		];

		$manager->register_user_fields($sections, $product);

		$user_roles = get_editable_roles();

		$this->assertArrayHasKey('state', $sections['users']);
		$this->assertArrayHasKey('roles', $sections['users']['state']);

		foreach ($user_roles as $role_slug => $role) {
			$this->assertArrayHasKey("control_{$role_slug}", $sections['users']['fields']);
		}
	}

	// ---------------------------------------------------------------
	// register_post_type_fields tests
	// ---------------------------------------------------------------

	/**
	 * Test register_post_type_fields adds fields for visible post types.
	 */
	public function test_register_post_type_fields_adds_post_type_fields(): void {

		$manager = $this->get_manager_instance();

		$product = new Product([
			'name' => 'Test Plan PT Fields',
			'slug' => 'test-plan-pt-fields',
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [
			'post_types' => [
				'title'  => 'Post Types',
				'fields' => [],
			],
		];

		$manager->register_post_type_fields($sections, $product);

		$this->assertArrayHasKey('state', $sections['post_types']);
		$this->assertArrayHasKey('types', $sections['post_types']['state']);

		// At minimum, 'post' and 'page' should be there
		$this->assertArrayHasKey('control_post', $sections['post_types']['fields']);
		$this->assertArrayHasKey('control_page', $sections['post_types']['fields']);
	}

	// ---------------------------------------------------------------
	// Product limitations
	// ---------------------------------------------------------------

	/**
	 * Test product get_limitations returns Limitations object.
	 */
	public function test_product_get_limitations_returns_limitations(): void {

		$product = new Product([
			'name' => 'Test Plan Get Limitations',
			'slug' => 'test-plan-get-limits',
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$limitations = $product->get_limitations();

		$this->assertInstanceOf(Limitations::class, $limitations);
	}

	/**
	 * Test product limitations_to_merge returns empty array.
	 */
	public function test_product_limitations_to_merge_is_empty(): void {

		$product = new Product([
			'name' => 'Test Plan Merge',
			'slug' => 'test-plan-merge',
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$this->assertEquals([], $product->limitations_to_merge());
	}

	// ---------------------------------------------------------------
	// Async operations tests
	// ---------------------------------------------------------------

	/**
	 * Test async_handle_plugins returns early for main site.
	 */
	public function test_async_handle_plugins_skips_main_site(): void {

		$manager = $this->get_manager_instance();

		$main_site_id = get_main_site_id();

		// This should not throw any errors - it just returns early
		$manager->async_handle_plugins('activate', $main_site_id, []);

		$this->assertTrue(true); // No exception thrown
	}

	/**
	 * Test async_switch_theme switches theme on given site.
	 */
	public function test_async_switch_theme(): void {

		$manager = $this->get_manager_instance();

		$blog_id = self::factory()->blog->create();

		$themes = wp_get_themes();
		if (count($themes) < 1) {
			$this->markTestSkipped('No themes available to test with.');
		}

		$theme_key = array_key_first($themes);

		$manager->async_switch_theme($blog_id, $theme_key);

		switch_to_blog($blog_id);
		$current_theme = get_stylesheet();
		restore_current_blog();

		$this->assertEquals($theme_key, $current_theme);
	}

	// ---------------------------------------------------------------
	// register_forms test
	// ---------------------------------------------------------------

	/**
	 * Test register_forms registers the confirmation form.
	 */
	public function test_register_forms(): void {

		$manager = $this->get_manager_instance();
		$manager->register_forms();

		// After registration, the form should exist. We just verify no error is thrown.
		$this->assertTrue(true);
	}

	// ---------------------------------------------------------------
	// Limitations build_modules and build tests
	// ---------------------------------------------------------------

	/**
	 * Test build_modules replaces internal data.
	 */
	public function test_limitations_build_modules(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$new_data = [
			'sites' => [
				'enabled' => true,
				'limit'   => 10,
			],
		];

		$result = $limitations->build_modules($new_data);

		$this->assertSame($limitations, $result);
		$this->assertEquals(10, $limitations->sites->get_limit());
	}

	/**
	 * Test Limitations::build for a known module.
	 */
	public function test_limitations_build_known_module(): void {

		$module = Limitations::build(
			[
				'enabled' => true,
				'limit'   => 42,
			],
			'sites'
		);

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Sites::class, $module);
		$this->assertEquals(42, $module->get_limit());
	}

	/**
	 * Test Limitations::build for an unknown module returns false.
	 */
	public function test_limitations_build_unknown_module_returns_false(): void {

		$module = Limitations::build([], 'nonexistent_module');

		$this->assertFalse($module);
	}

	/**
	 * Test Limitations::build with JSON string data.
	 */
	public function test_limitations_build_with_json_string(): void {

		$json = json_encode([
			'enabled' => true,
			'limit'   => 7,
		]);

		$module = Limitations::build($json, 'sites');

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Sites::class, $module);
		$this->assertEquals(7, $module->get_limit());
	}

	// ---------------------------------------------------------------
	// Plugin selection list test
	// ---------------------------------------------------------------

	/**
	 * Test get_plugin_selection_list returns a string.
	 */
	public function test_get_plugin_selection_list_returns_string(): void {

		$manager = $this->get_manager_instance();

		$product = new Product([
			'name' => 'Test Plan Plugin List',
			'slug' => 'test-plan-plugin-list',
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$result = $manager->get_plugin_selection_list($product);

		$this->assertIsString($result);
	}

	// ---------------------------------------------------------------
	// Post type limit - is_post_above_limit
	// ---------------------------------------------------------------

	/**
	 * Test post_types is_post_above_limit.
	 */
	public function test_post_types_is_post_above_limit(): void {

		$limitations = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [
					'post' => [
						'enabled' => true,
						'number'  => 5,
					],
				],
			],
		]);

		// With no posts created, should not be above limit
		// Note: is_post_above_limit depends on actual post count
		$result = $limitations->post_types->is_post_above_limit('post');

		// Result depends on actual post count, but we can at least verify it returns bool
		$this->assertIsBool($result);
	}

	// ---------------------------------------------------------------
	// Limit allowed method
	// ---------------------------------------------------------------

	/**
	 * Test allowed method respects enabled state.
	 */
	public function test_allowed_respects_enabled_state(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => false,
				'limit'   => 5,
			],
		]);

		// When module is disabled, allowed should return false
		$this->assertFalse($limitations->sites->allowed(1));
	}

	/**
	 * Test allowed method when enabled calls check.
	 */
	public function test_allowed_when_enabled_calls_check(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		// Limit_Sites::check() always returns true
		$this->assertTrue($limitations->sites->allowed(1));
	}

	/**
	 * Test allowed filter hook.
	 */
	public function test_allowed_filter_hook(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		add_filter('wu_limit_sites__allowed', function ($allowed) {
			return false;
		});

		$this->assertFalse($limitations->sites->allowed(1));

		remove_all_filters('wu_limit_sites__allowed');
	}

	// ---------------------------------------------------------------
	// Limitation modules from repository filter
	// ---------------------------------------------------------------

	/**
	 * Test wu_limit_classes filter can add custom limit classes.
	 */
	public function test_wu_limit_classes_filter(): void {

		add_filter('wu_limit_classes', function ($classes) {
			// Add a fake class that does not exist
			$classes['custom_limit'] = 'NonExistentClass';
			return $classes;
		});

		$repository = Limitations::repository();

		$this->assertArrayHasKey('custom_limit', $repository);

		remove_all_filters('wu_limit_classes');
	}

	// ---------------------------------------------------------------
	// Limitation_Manager init tests
	// ---------------------------------------------------------------

	/**
	 * Test init registers expected hooks.
	 */
	public function test_init_registers_hooks(): void {

		$manager = $this->get_manager_instance();

		$this->assertNotFalse(has_filter('wu_product_options_sections', [$manager, 'add_limitation_sections']));
		$this->assertNotFalse(has_filter('wu_membership_options_sections', [$manager, 'add_limitation_sections']));
		$this->assertNotFalse(has_filter('wu_site_options_sections', [$manager, 'add_limitation_sections']));
		$this->assertNotFalse(has_action('wu_async_handle_plugins', [$manager, 'async_handle_plugins']));
		$this->assertNotFalse(has_action('wu_async_switch_theme', [$manager, 'async_switch_theme']));
	}

	// ---------------------------------------------------------------
	// Limitation sections include visits when setting is enabled
	// ---------------------------------------------------------------

	/**
	 * Test visits section included when visits limiting is enabled.
	 */
	public function test_add_limitation_sections_includes_visits_when_enabled(): void {

		$manager = $this->get_manager_instance();

		wu_save_setting('enable_visits_limiting', true);

		$product = new Product([
			'name' => 'Test Plan Visits',
			'slug' => 'test-plan-visits-' . wp_rand(),
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $product);

		$this->assertArrayHasKey('visits', $sections);
	}

	/**
	 * Test visits section not included when visits limiting is disabled.
	 */
	public function test_add_limitation_sections_excludes_visits_when_disabled(): void {

		$manager = $this->get_manager_instance();

		wu_save_setting('enable_visits_limiting', false);

		$product = new Product([
			'name' => 'Test Plan No Visits',
			'slug' => 'test-plan-no-visits-' . wp_rand(),
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $product);

		$this->assertArrayNotHasKey('visits', $sections);

		// Restore default
		wu_save_setting('enable_visits_limiting', true);
	}

	// ---------------------------------------------------------------
	// Edge cases
	// ---------------------------------------------------------------

	/**
	 * Test Limitations with all modules disabled.
	 */
	public function test_limitations_all_disabled(): void {

		$data = [];
		foreach (array_keys(Limitations::repository()) as $module_name) {
			$data[ $module_name ] = [
				'enabled' => false,
				'limit'   => null,
			];
		}

		$limitations = new Limitations($data);

		$this->assertFalse($limitations->has_limitations());
	}

	/**
	 * Test Limitations with mixed enabled/disabled modules.
	 */
	public function test_limitations_mixed_enabled_disabled(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
			'disk_space' => [
				'enabled' => false,
				'limit'   => 100,
			],
		]);

		$this->assertTrue($limitations->has_limitations());
		$this->assertTrue($limitations->is_module_enabled('sites'));
		$this->assertFalse($limitations->is_module_enabled('disk_space'));
	}

	/**
	 * Test Limit setup with non-array data casts to array.
	 */
	public function test_limit_setup_with_non_array_data(): void {

		$limitations = new Limitations([
			'sites' => (object) [
				'enabled' => true,
				'limit'   => 3,
			],
		]);

		$sites = $limitations->sites;

		$this->assertInstanceOf(\WP_Ultimo\Limitations\Limit_Sites::class, $sites);
		$this->assertTrue($sites->is_enabled());
		$this->assertEquals(3, $sites->get_limit());
	}

	/**
	 * Test accessing the same module multiple times returns cached instance.
	 */
	public function test_module_access_is_cached(): void {

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$first  = $limitations->sites;
		$second = $limitations->sites;

		$this->assertSame($first, $second);
	}

	/**
	 * Test merging preserves enabled true when summing.
	 */
	public function test_merge_preserves_true_enabled_when_summing(): void {

		$base = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$override = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 3,
			],
		]);

		$merged = $base->merge($override);

		$this->assertTrue($merged->sites->is_enabled());
	}

	/**
	 * Test merging with disabled base and enabled addon.
	 */
	public function test_merge_disabled_base_enabled_addon(): void {

		$base = new Limitations([
			'sites' => [
				'enabled' => false,
			],
		]);

		$addon = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		$merged = $base->merge($addon);

		// When base is disabled, merge_recursive sets it to {enabled: false}
		// and addon's values should still apply based on the merge logic
		$arr = $merged->to_array();
		$this->assertArrayHasKey('sites', $arr);
	}

	/**
	 * Test get_all_plugins does not include network-only plugins.
	 */
	public function test_get_all_plugins_excludes_network_only(): void {

		$manager = $this->get_manager_instance();
		$plugins = $manager->get_all_plugins();

		// All returned plugins should NOT have Network = true
		foreach ($plugins as $plugin_info) {
			$network = wu_get_isset($plugin_info, 'Network', false);
			$this->assertNotTrue($network, 'Network-only plugins should be excluded.');
		}
	}

	/**
	 * Test plugins get_by_type for force_active behavior.
	 */
	public function test_plugins_get_by_type_force_active(): void {

		$limitations = new Limitations([
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'plugin-a/plugin-a.php' => [
						'visibility' => 'visible',
						'behavior'   => 'force_active',
					],
					'plugin-b/plugin-b.php' => [
						'visibility' => 'visible',
						'behavior'   => 'default',
					],
					'plugin-c/plugin-c.php' => [
						'visibility' => 'hidden',
						'behavior'   => 'force_active',
					],
				],
			],
		]);

		$forced_active = $limitations->plugins->get_by_type('force_active');

		$this->assertArrayHasKey('plugin-a/plugin-a.php', $forced_active);
		$this->assertArrayHasKey('plugin-c/plugin-c.php', $forced_active);
		$this->assertArrayNotHasKey('plugin-b/plugin-b.php', $forced_active);
	}

	/**
	 * Test plugins get_by_type for force_inactive behavior.
	 */
	public function test_plugins_get_by_type_force_inactive(): void {

		$limitations = new Limitations([
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'plugin-a/plugin-a.php' => [
						'visibility' => 'visible',
						'behavior'   => 'force_inactive',
					],
					'plugin-b/plugin-b.php' => [
						'visibility' => 'visible',
						'behavior'   => 'default',
					],
				],
			],
		]);

		$forced_inactive = $limitations->plugins->get_by_type('force_inactive');

		$this->assertArrayHasKey('plugin-a/plugin-a.php', $forced_inactive);
		$this->assertArrayNotHasKey('plugin-b/plugin-b.php', $forced_inactive);
	}

	/**
	 * Test plugins get_by_type with visibility filter.
	 */
	public function test_plugins_get_by_type_with_visibility(): void {

		$limitations = new Limitations([
			'plugins' => [
				'enabled' => true,
				'limit'   => [
					'plugin-a/plugin-a.php' => [
						'visibility' => 'hidden',
						'behavior'   => 'default',
					],
					'plugin-b/plugin-b.php' => [
						'visibility' => 'visible',
						'behavior'   => 'default',
					],
				],
			],
		]);

		$hidden = $limitations->plugins->get_by_type(null, 'hidden');

		$this->assertArrayHasKey('plugin-a/plugin-a.php', $hidden);
		$this->assertArrayNotHasKey('plugin-b/plugin-b.php', $hidden);
	}

	// ---------------------------------------------------------------
	// Limitation_Manager sections have correct structure
	// ---------------------------------------------------------------

	/**
	 * Test sites section has correct field structure.
	 */
	public function test_sites_section_has_fields(): void {

		$manager = $this->get_manager_instance();

		$product = new Product([
			'name' => 'Test Sites Fields',
			'slug' => 'test-sites-fields-' . wp_rand(),
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $product);

		$this->assertArrayHasKey('fields', $sections['sites']);
		$this->assertArrayHasKey('modules[sites][enabled]', $sections['sites']['fields']);
		$this->assertArrayHasKey('modules[sites][limit]', $sections['sites']['fields']);
	}

	/**
	 * Test disk space section has correct field structure.
	 */
	public function test_disk_space_section_has_fields(): void {

		$manager = $this->get_manager_instance();

		$product = new Product([
			'name' => 'Test Disk Fields',
			'slug' => 'test-disk-fields-' . wp_rand(),
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $product);

		$this->assertArrayHasKey('fields', $sections['limit_disk_space']);
		$this->assertArrayHasKey('modules[disk_space][enabled]', $sections['limit_disk_space']['fields']);
		$this->assertArrayHasKey('modules[disk_space][limit]', $sections['limit_disk_space']['fields']);
	}

	/**
	 * Test domain mapping section has correct field structure.
	 */
	public function test_domain_mapping_section_has_fields(): void {

		$manager = $this->get_manager_instance();

		$product = new Product([
			'name' => 'Test Domain Fields',
			'slug' => 'test-domain-fields-' . wp_rand(),
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $product);

		$this->assertArrayHasKey('custom_domain', $sections);
		$this->assertArrayHasKey('fields', $sections['custom_domain']);
		$this->assertArrayHasKey('modules[domain_mapping][enabled]', $sections['custom_domain']['fields']);
	}

	/**
	 * Test hide credits section has correct field structure.
	 */
	public function test_hide_credits_section_has_fields(): void {

		$manager = $this->get_manager_instance();

		$product = new Product([
			'name' => 'Test Credits Fields',
			'slug' => 'test-credits-fields-' . wp_rand(),
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $product);

		$this->assertArrayHasKey('hide_credits', $sections);
		$this->assertArrayHasKey('fields', $sections['hide_credits']);
		$this->assertArrayHasKey('modules[hide_credits][enabled]', $sections['hide_credits']['fields']);
	}

	// ---------------------------------------------------------------
	// Membership override notices
	// ---------------------------------------------------------------

	/**
	 * Test membership sections include override notices.
	 */
	public function test_membership_sections_include_override_notices(): void {

		$manager  = $this->get_manager_instance();
		$customer = $this->create_test_customer();

		$membership = new Membership([
			'customer_id' => $customer->get_id(),
			'status'      => 'active',
		]);
		$membership->set_skip_validation(true);
		$membership->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $membership);

		// For non-product models, override notices should be added
		// Check users section has override notice field
		$this->assertArrayHasKey('modules_user_overwrite', $sections['users']['fields']);
	}

	/**
	 * Test product sections do not include override notices.
	 */
	public function test_product_sections_no_override_notices(): void {

		$manager = $this->get_manager_instance();

		$product = new Product([
			'name' => 'Test No Override',
			'slug' => 'test-no-override-' . wp_rand(),
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		$sections = [];
		$sections = $manager->add_limitation_sections($sections, $product);

		// Products should NOT have override notices
		$this->assertArrayNotHasKey('modules_user_overwrite', $sections['users']['fields']);
	}

	// ---------------------------------------------------------------
	// Limitation early_get_limitations
	// ---------------------------------------------------------------

	/**
	 * Test early_get_limitations returns empty array when no limitations set.
	 */
	public function test_early_get_limitations_empty(): void {

		$product = new Product([
			'name' => 'Test Early Lim',
			'slug' => 'test-early-lim-' . wp_rand(),
			'type' => 'plan',
		]);
		$product->set_skip_validation(true);
		$product->save();

		// Clear cache
		$reflection = new \ReflectionClass(Limitations::class);
		$cache_prop = $reflection->getProperty('limitations_cache');
		$cache_prop->setAccessible(true);
		$cache_prop->setValue(null, []);

		$result = Limitations::early_get_limitations('product', $product->get_id());

		// Should return empty array or empty Limitations when no meta set
		$this->assertEmpty($result);
	}

	// ---------------------------------------------------------------
	// Limitations remove_limitations
	// ---------------------------------------------------------------

	/**
	 * Test remove_limitations does not error on nonexistent data.
	 */
	public function test_remove_limitations_no_error_on_missing(): void {

		// This should not throw any errors
		Limitations::remove_limitations('product', 99999);

		$this->assertTrue(true); // No exception
	}

	// ---------------------------------------------------------------
	// Limit setup action hook
	// ---------------------------------------------------------------

	/**
	 * Test limit setup action fires during construction.
	 */
	public function test_limit_setup_fires_action(): void {

		$action_fired = false;

		add_action('wu_sites_limit_setup', function () use (&$action_fired) {
			$action_fired = true;
		});

		$limitations = new Limitations([
			'sites' => [
				'enabled' => true,
				'limit'   => 5,
			],
		]);

		// Access the module to trigger construction
		$limitations->sites;

		$this->assertTrue($action_fired);

		remove_all_actions('wu_sites_limit_setup');
	}

	// ---------------------------------------------------------------
	// Merging post type limitations
	// ---------------------------------------------------------------

	/**
	 * Test merging post type limits sums numbers.
	 */
	public function test_merge_post_type_limits_sum(): void {

		$base = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [
					'post' => [
						'enabled' => true,
						'number'  => 10,
					],
				],
			],
		]);

		$addon = new Limitations([
			'post_types' => [
				'enabled' => true,
				'limit'   => [
					'post' => [
						'enabled' => true,
						'number'  => 5,
					],
				],
			],
		]);

		$merged = $base->merge($addon);

		$post = $merged->post_types->post;

		$this->assertEquals(15, $post->number);
	}

	/**
	 * Test merging user role limits sums numbers.
	 */
	public function test_merge_user_limits_sum(): void {

		$base = new Limitations([
			'users' => [
				'enabled' => true,
				'limit'   => [
					'editor' => [
						'enabled' => true,
						'number'  => 3,
					],
				],
			],
		]);

		$addon = new Limitations([
			'users' => [
				'enabled' => true,
				'limit'   => [
					'editor' => [
						'enabled' => true,
						'number'  => 2,
					],
				],
			],
		]);

		$merged = $base->merge($addon);

		$editor = $merged->users->editor;

		$this->assertEquals(5, $editor->number);
	}
}

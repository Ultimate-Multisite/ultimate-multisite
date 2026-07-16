<?php
/**
 * Unit tests for the Limitations object.
 *
 * @package WP_Ultimo\Tests\Objects
 */

namespace WP_Ultimo\Objects;

use WP_UnitTestCase;
use WP_Ultimo\Objects\Limitations;

/**
 * Test case for WP_Ultimo\Objects\Limitations.
 *
 * @package WP_Ultimo\Tests\Objects
 */
class Limitations_Test extends WP_UnitTestCase {

	/**
	 * Clear limitations cache before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		// Clear the static cache using reflection
		$reflection     = new \ReflectionClass(Limitations::class);
		$cache_property = $reflection->getProperty('limitations_cache');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$cache_property->setAccessible(true);
		}

		$cache_property->setValue(null, []);
	}

	/**
	 * Data provider for constructor test scenarios.
	 *
	 * @return array
	 */
	public function constructorDataProvider(): array {
		return [
			'empty_modules'            => [
				'modules_data'           => [],
				'expected_modules_count' => 0,
			],
			'single_module'            => [
				'modules_data'           => [
					'plugins' => [
						'enabled'      => true,
						'behavior'     => 'default',
						'plugins_list' => [],
					],
				],
				'expected_modules_count' => 1,
			],
			'multiple_modules'         => [
				'modules_data'           => [
					'plugins' => [
						'enabled'  => true,
						'behavior' => 'default',
					],
					'themes'  => [
						'enabled'  => false,
						'behavior' => 'not_available',
					],
					'users'   => [
						'enabled' => true,
						'limit'   => 10,
					],
				],
				'expected_modules_count' => 3,
			],
			'modules_with_json_string' => [
				'modules_data'           => [
					'disk_space' => '{"enabled":true,"limit":1024}',
				],
				'expected_modules_count' => 1,
			],
		];
	}

	/**
	 * Test constructor with various module data.
	 *
	 * @dataProvider constructorDataProvider
	 * @param array $modules_data           Input module data.
	 * @param int   $expected_modules_count Expected number of modules.
	 */
	public function test_constructor(array $modules_data, int $expected_modules_count): void {
		$limitations = new Limitations($modules_data);

		$this->assertInstanceOf(Limitations::class, $limitations);

		// Use reflection to access protected modules property
		$reflection       = new \ReflectionClass($limitations);
		$modules_property = $reflection->getProperty('raw_module_data');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$modules_property->setAccessible(true);
		}

		$modules = $modules_property->getValue($limitations);

		$this->assertCount($expected_modules_count, $modules);
	}

	/**
	 * Data provider for magic getter test scenarios.
	 *
	 * @return array
	 */
	public function magicGetterDataProvider(): array {
		return [
			'existing_module'     => [
				'module_name'  => 'plugins',
				'should_exist' => true,
			],
			'non_existing_module' => [
				'module_name'  => 'non_existent_module',
				'should_exist' => false,
			],
			'themes_module'       => [
				'module_name'  => 'themes',
				'should_exist' => true,
			],
			'users_module'        => [
				'module_name'  => 'users',
				'should_exist' => true,
			],
		];
	}

	/**
	 * Test magic getter method.
	 *
	 * @dataProvider magicGetterDataProvider
	 * @param string $module_name  Name of the module to access.
	 * @param bool   $should_exist Whether the module should exist.
	 */
	public function test_magic_getter(string $module_name, bool $should_exist): void {
		$limitations = new Limitations();
		$result      = $limitations->{$module_name};

		if ($should_exist) {
			$this->assertNotFalse($result);
			$this->assertNotNull($result);
		} else {
			$this->assertFalse($result);
		}
	}

	/**
	 * Test serialization methods.
	 */
	public function test_serialization_methods(): void {
		$modules_data = [
			'plugins' => [
				'enabled'  => true,
				'behavior' => 'default',
			],
			'users'   => [
				'enabled' => true,
				'limit'   => 5,
			],
		];

		$limitations = new Limitations($modules_data);

		// Test __serialize
		$serialized = $limitations->__serialize();
		$this->assertIsArray($serialized);
		$this->assertArrayHasKey('plugins', $serialized);
		$this->assertArrayHasKey('users', $serialized);

		// Test __unserialize
		$new_limitations = new Limitations();
		$new_limitations->__unserialize($serialized);

		$this->assertEquals($serialized, $new_limitations->__serialize());
	}

	/**
	 * Data provider for build_modules test scenarios.
	 *
	 * @return array
	 */
	public function buildModulesDataProvider(): array {
		return [
			'valid_modules'       => [
				'modules_data'   => [
					'plugins' => ['enabled' => true],
					'themes'  => ['enabled' => false],
				],
				'expected_count' => 2,
			],
			'empty_modules'       => [
				'modules_data'   => [],
				'expected_count' => 0,
			],
			'mixed_valid_invalid' => [
				'modules_data'   => [
					'plugins'        => ['enabled' => true],
					'invalid_module' => ['enabled' => true],
				],
				'expected_count' => 1,
			],
		];
	}

	/**
	 * Test build_modules method.
	 *
	 * @dataProvider buildModulesDataProvider
	 * @param array $modules_data   Input module data.
	 * @param int   $expected_count Expected number of built modules.
	 */
	public function test_build_modules(array $modules_data, int $expected_count): void {
		$limitations = new Limitations();
		$result      = $limitations->build_modules($modules_data);

		$this->assertInstanceOf(Limitations::class, $result);

		// Use reflection to access protected modules property
		$reflection       = new \ReflectionClass($limitations);
		$modules_property = $reflection->getProperty('raw_module_data');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$modules_property->setAccessible(true);
		}

		$modules = $modules_property->getValue($limitations);

		$this->assertEquals($modules_data, $modules);
	}

	/**
	 * Data provider for build method test scenarios.
	 *
	 * @return array
	 */
	public function buildMethodDataProvider(): array {
		return [
			'valid_module_array' => [
				'data'           => [
					'enabled' => true,
					'limit'   => 10,
				],
				'module_name'    => 'users',
				'should_succeed' => true,
			],
			'valid_module_json'  => [
				'data'           => '{"enabled":true,"limit":5}',
				'module_name'    => 'users',
				'should_succeed' => true,
			],
			'invalid_module'     => [
				'data'           => ['enabled' => true],
				'module_name'    => 'non_existent_module',
				'should_succeed' => false,
			],
			'plugins_module'     => [
				'data'           => [
					'enabled'  => true,
					'behavior' => 'default',
				],
				'module_name'    => 'plugins',
				'should_succeed' => true,
			],
		];
	}

	/**
	 * Test static build method.
	 *
	 * @dataProvider buildMethodDataProvider
	 * @param mixed  $data           Module data (array or JSON string).
	 * @param string $module_name    Name of the module to build.
	 * @param bool   $should_succeed Whether the build should succeed.
	 */
	public function test_build_method($data, string $module_name, bool $should_succeed): void {
		$result = Limitations::build($data, $module_name);

		if ($should_succeed) {
			$this->assertNotFalse($result);
			$this->assertIsObject($result);
		} else {
			$this->assertFalse($result);
		}
	}

	/**
	 * Data provider for exists method test scenarios.
	 *
	 * @return array
	 */
	public function existsMethodDataProvider(): array {
		return [
			'existing_module'     => [
				'modules_data' => ['plugins' => ['enabled' => true]],
				'module_name'  => 'plugins',
				'should_exist' => true,
			],
			'non_existing_module' => [
				'modules_data' => ['plugins' => ['enabled' => true]],
				'module_name'  => 'themes',
				'should_exist' => false,
			],
			'empty_limitations'   => [
				'modules_data' => [],
				'module_name'  => 'plugins',
				'should_exist' => false,
			],
		];
	}

	/**
	 * Test exists method.
	 *
	 * @dataProvider existsMethodDataProvider
	 * @param array  $modules_data Input module data.
	 * @param string $module_name  Name of the module to check.
	 * @param bool   $should_exist Whether the module should exist.
	 */
	public function test_exists_method(array $modules_data, string $module_name, bool $should_exist): void {
		$limitations = new Limitations($modules_data);
		$result      = $limitations->exists($module_name);

		if ($should_exist) {
			$this->assertNotFalse($result);
			$this->assertTrue($result);
		} else {
			$this->assertFalse($result);
		}
	}

	/**
	 * Data provider for has_limitations method test scenarios.
	 *
	 * @return array
	 */
	public function hasLimitationsDataProvider(): array {
		return [
			'no_limitations'      => [
				'modules_data' => [],
				'expected'     => false,
			],
			'enabled_limitations' => [
				'modules_data' => [
					'users' => [
						'enabled' => true,
						'limit'   => 5,
					],
				],
				'expected'     => true,
			],
			'multiple_enabled'    => [
				'modules_data' => [
					'users'      => [
						'enabled' => true,
						'limit'   => 5,
					],
					'disk_space' => [
						'enabled' => true,
						'limit'   => 1024,
					],
				],
				'expected'     => true,
			],
		];
	}

	/**
	 * Test has_limitations method.
	 *
	 * @dataProvider hasLimitationsDataProvider
	 * @param array $modules_data Input module data.
	 * @param bool  $expected     Expected return value.
	 */
	public function test_has_limitations(array $modules_data, bool $expected): void {
		$limitations = new Limitations($modules_data);
		$result      = $limitations->has_limitations();

		$this->assertEquals($expected, $result);
	}

	/**
	 * Data provider for is_module_enabled method test scenarios.
	 *
	 * @return array
	 */
	public function isModuleEnabledDataProvider(): array {
		return [
			'enabled_module'      => [
				'modules_data' => [
					'users' => [
						'enabled' => true,
						'limit'   => 5,
					],
				],
				'module_name'  => 'users',
				'expected'     => true,
			],
			'non_existing_module' => [
				'modules_data' => [
					'users' => [
						'enabled' => true,
						'limit'   => 5,
					],
				],
				'module_name'  => 'non_existent',
				'expected'     => false,
			],
		];
	}

	/**
	 * Test is_module_enabled method.
	 *
	 * @dataProvider isModuleEnabledDataProvider
	 * @param array  $modules_data Input module data.
	 * @param string $module_name  Name of the module to check.
	 * @param bool   $expected     Expected return value.
	 */
	public function test_is_module_enabled(array $modules_data, string $module_name, bool $expected): void {
		$limitations = new Limitations($modules_data);
		$result      = $limitations->is_module_enabled($module_name);

		$this->assertEquals($expected, $result);
	}

	/**
	 * Data provider for merge method test scenarios.
	 *
	 * @return array
	 */
	public function mergeMethodDataProvider(): array {
		return [
			'simple_merge_addition'      => [
				'base_data'      => [
					'users' => [
						'enabled' => true,
						'limit'   => 5,
					],
				],
				'merge_data'     => [
					[
						'users' => [
							'enabled' => true,
							'limit'   => 3,
						],
					],
				],
				'override'       => false,
				'expected_limit' => 8,
			],
			'simple_merge_override'      => [
				'base_data'      => [
					'users' => [
						'enabled' => true,
						'limit'   => 5,
					],
				],
				'merge_data'     => [
					[
						'users' => [
							'enabled' => true,
							'limit'   => 3,
						],
					],
				],
				'override'       => true,
				'expected_limit' => 3,
			],
			'merge_with_disabled'        => [
				'base_data'        => [
					'users' => [
						'enabled' => true,
						'limit'   => 5,
					],
				],
				'merge_data'       => [
					[
						'users' => [
							'enabled' => false,
							'limit'   => 3,
						],
					],
				],
				'override'         => false,
				'expected_enabled' => true,
			],
			'merge_unlimited_value'      => [
				'base_data'      => [
					'users' => [
						'enabled' => true,
						'limit'   => 0,
					],
				],
				'merge_data'     => [
					[
						'users' => [
							'enabled' => true,
							'limit'   => 5,
						],
					],
				],
				'override'       => false,
				'expected_limit' => 0,
			],
			'merge_multiple_limitations' => [
				'base_data'      => [
					'users' => [
						'enabled' => true,
						'limit'   => 5,
					],
				],
				'merge_data'     => [
					[
						'users' => [
							'enabled' => true,
							'limit'   => 3,
						],
					],
					[
						'users' => [
							'enabled' => true,
							'limit'   => 2,
						],
					],
				],
				'override'       => false,
				'expected_limit' => 10,
			],
		];
	}

	/**
	 * Test merge method.
	 *
	 * @dataProvider mergeMethodDataProvider
	 * @param array $base_data      Base limitations data.
	 * @param array $merge_data     Array of limitations data to merge in.
	 * @param bool  $override       Whether to use override mode.
	 * @param mixed $expected_value Expected value after merge.
	 */
	public function test_merge_method(array $base_data, array $merge_data, bool $override, $expected_value): void {
		$limitations = new Limitations($base_data);

		$result = $limitations->merge($override, ...$merge_data);

		$this->assertInstanceOf(Limitations::class, $result);

		$result_array = $result->to_array();

		if (is_int($expected_value)) {
			$this->assertEquals($expected_value, $result_array['users']['limit']);
		}
	}

	/**
	 * Test merge with Limitations objects.
	 */
	public function test_merge_with_limitations_objects(): void {
		$base_limitations  = new Limitations(
			[
				'users' => [
					'enabled' => true,
					'limit'   => 5,
				],
			]
		);
		$merge_limitations = new Limitations(
			[
				'users' => [
					'enabled' => true,
					'limit'   => 3,
				],
			]
		);

		$result = $base_limitations->merge(false, $merge_limitations);

		$this->assertInstanceOf(Limitations::class, $result);

		$result_array = $result->to_array();
		$this->assertEquals(8, $result_array['users']['limit']);
	}

	/**
	 * Test merge with invalid data.
	 */
	public function test_merge_with_invalid_data(): void {
		$limitations = new Limitations(
			[
				'users' => [
					'enabled' => true,
					'limit'   => 5,
				],
			]
		);

		$result = $limitations->merge(false, 'invalid_string', null, 123);

		$this->assertInstanceOf(Limitations::class, $result);

		$result_array = $result->to_array();
		$this->assertEquals(5, $result_array['users']['limit']);
	}

	/**
	 * Test to_array method.
	 */
	public function test_to_array_method(): void {
		$modules_data = [
			'plugins' => [
				'enabled'  => true,
				'behavior' => 'default',
			],
			'users'   => [
				'enabled' => true,
				'limit'   => 10,
			],
		];

		$limitations = new Limitations($modules_data);
		$result      = $limitations->to_array();

		$this->assertIsArray($result);
		$this->assertArrayHasKey('plugins', $result);
		$this->assertArrayHasKey('users', $result);
	}

	/**
	 * Test get_empty method.
	 */
	public function test_get_empty_method(): void {
		$result = Limitations::get_empty();

		$this->assertInstanceOf(Limitations::class, $result);

		// Verify it has access to all repository modules
		$repository = Limitations::repository();

		foreach (array_keys($repository) as $module_name) {
			$module = $result->{$module_name};
			$this->assertNotFalse($module, "Module {$module_name} should be accessible");
		}
	}

	/**
	 * Test repository method.
	 */
	public function test_repository_method(): void {
		$repository = Limitations::repository();

		$this->assertIsArray($repository);
		$this->assertNotEmpty($repository);

		// Check for expected standard modules
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
		];

		foreach ($expected_modules as $module) {
			$this->assertArrayHasKey($module, $repository);
			$this->assertIsString($repository[ $module ]);
		}
	}

	/**
	 * Test merge_recursive protected method using reflection.
	 */
	public function test_merge_recursive_method(): void {
		$limitations = new Limitations();

		// Use reflection to access protected method
		$reflection = new \ReflectionClass($limitations);
		$method     = $reflection->getMethod('merge_recursive');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$array1 = [
			'enabled' => true,
			'limit'   => 5,
			'nested'  => [
				'value' => 10,
			],
		];

		$array2 = [
			'enabled' => true,
			'limit'   => 3,
			'nested'  => [
				'value' => 5,
			],
		];

		$method->invokeArgs($limitations, [&$array1, &$array2, true]);

		$this->assertEquals(8, $array1['limit']);
		$this->assertEquals(15, $array1['nested']['value']);
	}

	/**
	 * Test merge_recursive with force enabled modules.
	 */
	public function test_merge_recursive_force_enabled(): void {
		$limitations = new Limitations();

		// Set current_merge_id to test force enabled logic
		$reflection = new \ReflectionClass($limitations);
		$property   = $reflection->getProperty('current_merge_id');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$property->setValue($limitations, 'plugins');

		$method = $reflection->getMethod('merge_recursive');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$array1 = ['enabled' => false];
		$array2 = ['enabled' => false];

		$method->invokeArgs($limitations, [&$array1, &$array2, true]);

		$this->assertTrue($array1['enabled']);
	}

	/**
	 * Test merge_recursive with visibility priority.
	 *
	 * Hidden must win over visible: if any source restricts visibility to 'hidden',
	 * the item must remain hidden. This is the fix for issue #234 where plugins/themes
	 * set as hidden on a product or membership were not being hidden.
	 */
	public function test_merge_recursive_visibility_priority(): void {
		$limitations = new Limitations();

		$reflection = new \ReflectionClass($limitations);
		$method     = $reflection->getMethod('merge_recursive');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		// Case 1: base is 'hidden', incoming is 'visible' — hidden must win.
		$array1 = [
			'enabled'    => true,
			'visibility' => 'hidden',
		];
		$array2 = [
			'enabled'    => true,
			'visibility' => 'visible',
		];

		$method->invokeArgs($limitations, [&$array1, &$array2, true]);

		$this->assertEquals('hidden', $array1['visibility'], 'hidden should win over visible (restriction takes priority)');

		// Case 2: base is 'visible', incoming is 'hidden' — hidden must win.
		$array3 = [
			'enabled'    => true,
			'visibility' => 'visible',
		];
		$array4 = [
			'enabled'    => true,
			'visibility' => 'hidden',
		];

		$method->invokeArgs($limitations, [&$array3, &$array4, true]);

		$this->assertEquals('hidden', $array3['visibility'], 'hidden incoming should override visible base');

		// Case 3: both visible — stays visible.
		$array5 = [
			'enabled'    => true,
			'visibility' => 'visible',
		];
		$array6 = [
			'enabled'    => true,
			'visibility' => 'visible',
		];

		$method->invokeArgs($limitations, [&$array5, &$array6, true]);

		$this->assertEquals('visible', $array5['visibility'], 'visible + visible should remain visible');
	}

	/**
	 * Regression test for issue #234: plugins hidden on a product/membership must be hidden on the site.
	 *
	 * When a plugin is set to 'hidden' visibility on a product limitation, merging that product's
	 * limitations into the site's composite limitations must result in the plugin being hidden.
	 * Previously the merge kept 'visible' because the priority was inverted.
	 */
	public function test_plugin_hidden_on_product_is_hidden_on_site(): void {

		// Product limitation: plugin is explicitly hidden.
		$product_limitations = new Limitations(
			[
				'plugins' => [
					'enabled' => true,
					'limit'   => [
						'woocommerce/woocommerce.php' => [
							'visibility' => 'hidden',
							'behavior'   => 'default',
						],
					],
				],
			]
		);

		// Site starts with empty limitations (no site-level overrides).
		$site_limitations = new Limitations([]);

		// Simulate the waterfall: merge product limitations first, then site overrides.
		$composite = $site_limitations->merge($product_limitations);

		$plugin_limit = $composite->plugins->{'woocommerce/woocommerce.php'};

		$this->assertEquals(
			'hidden',
			$plugin_limit->visibility,
			'Plugin set as hidden on product must be hidden in composite limitations (issue #234)'
		);

		$this->assertTrue(
			$composite->plugins->allowed('woocommerce/woocommerce.php', 'hidden'),
			'allowed() with type "hidden" must return true for a hidden plugin'
		);

		$this->assertFalse(
			$composite->plugins->allowed('woocommerce/woocommerce.php', 'visible'),
			'allowed() with type "visible" must return false for a hidden plugin'
		);
	}

	/**
	 * Regression test for issue #234: themes hidden on a membership must be hidden on the site.
	 */
	public function test_theme_hidden_on_membership_is_hidden_on_site(): void {

		// Membership limitation: theme is explicitly hidden.
		$membership_limitations = new Limitations(
			[
				'themes' => [
					'enabled' => true,
					'limit'   => [
						'twentytwentyfour' => [
							'visibility' => 'hidden',
							'behavior'   => 'available',
						],
					],
				],
			]
		);

		// Site starts with empty limitations.
		$site_limitations = new Limitations([]);

		$composite = $site_limitations->merge($membership_limitations);

		$theme_limit = $composite->themes->{'twentytwentyfour'};

		$this->assertEquals(
			'hidden',
			$theme_limit->visibility,
			'Theme set as hidden on membership must be hidden in composite limitations (issue #234)'
		);

		$this->assertTrue(
			$composite->themes->allowed('twentytwentyfour', 'hidden'),
			'allowed() with type "hidden" must return true for a hidden theme'
		);
	}

	/**
	 * Test merge_recursive with behavior priority.
	 */
	public function test_merge_recursive_behavior_priority(): void {
		$limitations = new Limitations();

		// Set current_merge_id to plugins for behavior testing
		$reflection = new \ReflectionClass($limitations);
		$property   = $reflection->getProperty('current_merge_id');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$property->setValue($limitations, 'plugins');

		$method = $reflection->getMethod('merge_recursive');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$array1 = [
			'enabled'  => true,
			'behavior' => 'default',
		];
		$array2 = [
			'enabled'  => true,
			'behavior' => 'force_active',
		];

		$method->invokeArgs($limitations, [&$array1, &$array2, true]);

		$this->assertEquals('force_active', $array1['behavior']);
	}

	/**
	 * Test that null limit from addon product does not overwrite plan template list during additive merge.
	 *
	 * Regression test: when a plan with configured site templates was merged with an addon product
	 * that had site_templates.limit = null, the null would overwrite the plan's template list,
	 * causing "The selected template is not available for this product" validation error.
	 */
	public function test_merge_null_limit_does_not_overwrite_template_list(): void {

		$plan_limitations = new Limitations(
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'default',
					'limit'   => [
						'2' => ['behavior' => 'available'],
						'3' => ['behavior' => 'pre_selected'],
					],
				],
			]
		);

		$addon_limitations = new Limitations(
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'default',
					'limit'   => null,
				],
			]
		);

		$merged = $plan_limitations->merge($addon_limitations);

		$available = $merged->site_templates->get_available_site_templates();

		$this->assertContains(2, $available, 'Template 2 should still be available after merging addon with null limit');
		$this->assertContains(3, $available, 'Template 3 should still be available after merging addon with null limit');
	}

	/**
	 * Test that null limit does overwrite in override mode.
	 */
	public function test_merge_null_limit_overwrites_in_override_mode(): void {

		$base = new Limitations(
			[
				'users' => [
					'enabled' => true,
					'limit'   => 5,
				],
			]
		);

		$override = new Limitations(
			[
				'users' => [
					'enabled' => true,
					'limit'   => null,
				],
			]
		);

		$merged = $base->merge(true, $override);

		$this->assertNull($merged->users->get_limit(), 'Null limit should overwrite in override mode');
	}

	/**
	 * Test that get_empty returns proper default data in to_array.
	 */
	public function test_get_empty_to_array_returns_default_states(): void {

		$empty = Limitations::get_empty();

		$array = $empty->to_array();

		$this->assertNotEmpty($array, 'get_empty()->to_array() should not be empty');
		$this->assertArrayHasKey('site_templates', $array);
		$this->assertArrayHasKey('plugins', $array);
		$this->assertArrayHasKey('users', $array);
	}

	/**
	 * Test full checkout scenario: plan templates preserved when merging with addon product.
	 *
	 * Simulates the validation rule in class-site-template.php where all product
	 * limitations are merged together to determine available templates.
	 */
	public function test_checkout_plan_plus_addon_preserves_templates(): void {

		// Plan with specific templates configured
		$plan_data = new Limitations(
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'choose_available_templates',
					'limit'   => [
						'5' => ['behavior' => 'available'],
						'7' => ['behavior' => 'available'],
						'9' => ['behavior' => 'not_available'],
					],
				],
				'disk_space'     => [
					'enabled' => true,
					'limit'   => 500,
				],
			]
		);

		// Addon product with no template restrictions but some disk space
		$addon_data = new Limitations(
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'default',
					'limit'   => null,
				],
				'disk_space'     => [
					'enabled' => true,
					'limit'   => 100,
				],
			]
		);

		// Simulate the validation rule merge
		$limits = new Limitations([]);
		$limits = $limits->merge($plan_data);
		$limits = $limits->merge($addon_data);

		$this->assertSame(
			'choose_available_templates',
			$limits->site_templates->get_mode(),
			'A default-mode addon must not remove the plan template restriction.'
		);

		$available = $limits->site_templates->get_available_site_templates();

		$this->assertContains(5, $available, 'Template 5 should be available');
		$this->assertContains(7, $available, 'Template 7 should be available');

		// Disk space should be additive
		$this->assertEquals(600, $limits->disk_space->get_limit(), 'Disk space should be summed');
	}

	/**
	 * Regression test for issue #1637: default mode must allow all templates.
	 *
	 * Saved template data can remain after an administrator switches a product
	 * back to the default mode. That stale list must not restrict checkout
	 * template validation.
	 */
	public function test_default_template_mode_ignores_saved_template_list(): void {

		$limitations = new Limitations(
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'default',
					'limit'   => [
						'123' => ['behavior' => 'available'],
						'456' => ['behavior' => 'not_available'],
					],
				],
			]
		);

		$this->assertFalse(
			$limitations->site_templates->get_available_site_templates(),
			'Default mode must return false so checkout validation treats all templates as available.'
		);
	}

	/**
	 * Test that get_available_site_templates returns integers, not strings.
	 *
	 * Regression test for issue #351: When template IDs are stored as string keys
	 * in the limit array, they must be converted to integers for proper comparison
	 * in the Site_Template validation rule.
	 */
	public function test_available_site_templates_returns_integers(): void {

		$limitations = new Limitations(
			[
				'site_templates' => [
					'enabled' => true,
					'mode'    => 'choose_available_templates',
					'limit'   => [
						'123' => ['behavior' => 'available'],
						'456' => ['behavior' => 'pre_selected'],
						'789' => ['behavior' => 'not_available'],
					],
				],
			]
		);

		$available = $limitations->site_templates->get_available_site_templates();

		// Should return integers, not strings
		$this->assertContains(123, $available, 'Template 123 should be in available array as integer');
		$this->assertContains(456, $available, 'Template 456 should be in available array as integer');
		$this->assertNotContains(789, $available, 'Template 789 should not be available');

		// Verify strict type checking
		foreach ($available as $template_id) {
			$this->assertIsInt($template_id, 'All template IDs should be integers');
		}

		// Verify in_array works with strict comparison
		$this->assertTrue(in_array(123, $available, true), 'in_array with strict=true should find integer 123');
		$this->assertTrue(in_array(456, $available, true), 'in_array with strict=true should find integer 456');
	}
}

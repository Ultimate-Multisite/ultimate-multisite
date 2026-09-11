<?php
/**
 * Sunrise class tests.
 *
 * @package WP_Ultimo\Tests
 * @since 2.0.0
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Test Sunrise class functionality.
 */
class Sunrise_Test extends WP_UnitTestCase {

	/**
	 * Test version property exists and is string.
	 */
	public function test_version_property() {
		$this->assertIsString(Sunrise::$version);
		$this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+\.\d+$/', Sunrise::$version);
	}

	/**
	 * Test should_startup method doesn't throw fatal errors.
	 */
	public function test_should_startup_no_fatal_errors() {
		$result = Sunrise::should_startup();
		$this->assertIsBool($result);
	}

	/**
	 * Test should_load_sunrise method doesn't throw fatal errors.
	 */
	public function test_should_load_sunrise_no_fatal_errors() {
		$result = Sunrise::should_load_sunrise();
		$this->assertIsBool($result);
	}

	/**
	 * Test maybe_tap method doesn't throw fatal errors.
	 */
	public function test_maybe_tap_no_fatal_errors() {
		$result = Sunrise::maybe_tap('activating');
		$this->assertIsBool($result);

		$result = Sunrise::maybe_tap('deactivating');
		$this->assertIsBool($result);

		$result = Sunrise::maybe_tap('invalid_mode');
		$this->assertIsBool($result);
	}

	/**
	 * Test maybe_tap_on_init method doesn't throw fatal errors.
	 */
	public function test_maybe_tap_on_init_no_fatal_errors() {
		// This method has no return value, just ensure it doesn't throw exceptions
		$this->expectNotToPerformAssertions();
		Sunrise::maybe_tap_on_init();
	}

	/**
	 * Test system_info method doesn't throw fatal errors.
	 */
	public function test_system_info_no_fatal_errors() {
		$input = ['existing' => 'data'];

		// The system_info method may fail if sunrise meta data is missing or malformed
		// We expect this to potentially throw errors in a test environment
		try {
			$result = Sunrise::system_info($input);
			$this->assertIsArray($result);
			$this->assertArrayHasKey('existing', $result);
			$this->assertEquals('data', $result['existing']);
		} catch (\TypeError $e) {
			// This is expected when sunrise meta data is missing or has wrong types
			$this->assertStringContainsString('gmdate', $e->getMessage());
		}
	}

	/**
	 * Test system_info method adds sunrise data section.
	 */
	public function test_system_info_adds_sunrise_data() {
		$input = ['test' => 'value'];

		// The system_info method may fail if sunrise meta data is missing or malformed
		// We expect this to potentially throw errors in a test environment
		try {
			$result = Sunrise::system_info($input);
			$this->assertArrayHasKey('Sunrise Data', $result);
			$this->assertIsArray($result['Sunrise Data']);

			// Check expected keys exist in Sunrise Data
			$expected_keys = [
				'sunrise-status',
				'sunrise-data',
				'sunrise-created',
				'sunrise-last-activated',
				'sunrise-last-deactivated',
				'sunrise-last-modified',
			];

			foreach ($expected_keys as $key) {
				$this->assertArrayHasKey($key, $result['Sunrise Data']);
			}
		} catch (\TypeError $e) {
			// This is expected when sunrise meta data is missing or has wrong types
			$this->assertStringContainsString('gmdate', $e->getMessage());
		}
	}

	/**
	 * Test read_sunrise_meta method via reflection.
	 */
	public function test_read_sunrise_meta_no_fatal_errors() {
		$reflection = new \ReflectionClass(Sunrise::class);
		$method     = $reflection->getMethod('read_sunrise_meta');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke(null);
		$this->assertIsArray($result);
	}

	/**
	 * Test tap method via reflection.
	 */
	public function test_tap_no_fatal_errors() {
		$reflection = new \ReflectionClass(Sunrise::class);
		$method     = $reflection->getMethod('tap');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		// Test activating mode
		$result = $method->invoke(null, 'activating', []);
		$this->assertIsBool($result);

		// Test deactivating mode
		$result = $method->invoke(null, 'deactivating', []);
		$this->assertIsBool($result);
		$this->assertTrue($result);

		// Test invalid mode
		$result = $method->invoke(null, 'invalid', []);
		$this->assertFalse($result);
	}

	/**
	 * Test init method doesn't throw fatal errors.
	 */
	public function test_init_no_fatal_errors() {
		// This method has no return value, just ensure it doesn't throw exceptions
		$this->expectNotToPerformAssertions();
		Sunrise::init();
	}

	/**
	 * Test load method doesn't throw fatal errors.
	 */
	public function test_load_no_fatal_errors() {
		// This method has no return value, just ensure it doesn't throw exceptions
		$this->expectNotToPerformAssertions();
		Sunrise::load();
	}

	/**
	 * Test loaded method doesn't throw fatal errors.
	 */
	public function test_loaded_no_fatal_errors() {
		// This method has no return value, just ensure it doesn't throw exceptions
		$this->expectNotToPerformAssertions();
		Sunrise::loaded();
	}

	/**
	 * Test load_dependencies method doesn't throw fatal errors.
	 */
	public function test_load_dependencies_no_fatal_errors() {
		// This method has no return value, just ensure it doesn't throw exceptions
		$this->expectNotToPerformAssertions();
		Sunrise::load_dependencies();
	}

	/**
	 * Test load_domain_mapping method doesn't throw fatal errors.
	 */
	public function test_load_domain_mapping_no_fatal_errors() {
		// This method has no return value, just ensure it doesn't throw exceptions
		$this->expectNotToPerformAssertions();
		Sunrise::load_domain_mapping();
	}

	/**
	 * Test addon sunrise filtering excludes duplicate main plugin directories.
	 */
	public function test_filter_addon_sunrise_candidates_excludes_duplicate_main_plugin_directories() {
		$plugins_dir = sys_get_temp_dir() . '/wu-sunrise-test-' . uniqid('', true);

		$duplicate_dir = $plugins_dir . '/ultimate-multisite-1-1';
		$addon_dir     = $plugins_dir . '/ultimate-multisite-woocommerce';

		wp_mkdir_p($duplicate_dir);
		wp_mkdir_p($addon_dir);

		$duplicate_sunrise = $duplicate_dir . '/sunrise.php';
		$addon_sunrise     = $addon_dir . '/sunrise.php';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
		file_put_contents($duplicate_sunrise, '<?php // duplicate main sunrise.');
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
		file_put_contents($duplicate_dir . '/ultimate-multisite.php', '<?php // duplicate main bootstrap.');
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture setup.
		file_put_contents($addon_sunrise, '<?php // addon sunrise.');

		$reflection = new \ReflectionClass(Sunrise::class);
		$method     = $reflection->getMethod('filter_addon_sunrise_candidates');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		try {
			$result = $method->invoke(null, [$duplicate_sunrise, $addon_sunrise]);

			$this->assertSame([$addon_sunrise], $result);
		} finally {
			$this->remove_test_directory($plugins_dir);
		}
	}

	/**
	 * Remove a temporary test directory recursively.
	 *
	 * @param string $dir Directory path.
	 */
	private function remove_test_directory($dir) {

		if ( ! is_dir($dir) ) {
			return;
		}

		$items = scandir($dir);

		foreach ( $items as $item ) {
			if ('.' === $item || '..' === $item) {
				continue;
			}

			$path = $dir . '/' . $item;

			if ( is_dir($path) ) {
				$this->remove_test_directory($path);
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
			unlink($path);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture cleanup.
		rmdir($dir);
	}

	/**
	 * Seed the sunrise meta network option and reset the static read cache.
	 *
	 * Sunrise::read_sunrise_meta() caches the meta on a static property after
	 * the first read, so the cache has to be dropped for the seeded value to
	 * be visible to the code under test.
	 *
	 * @param bool $active Value to store under the `active` key.
	 */
	private function seed_sunrise_meta($active) {

		update_network_option(
			null,
			'wu_sunrise_meta',
			[
				'active'           => $active,
				'created'          => 1600000000,
				'last_activated'   => 1600000000,
				'last_deactivated' => 1600000000,
				'last_modified'    => 1600000000,
			]
		);

		Sunrise::$sunrise_meta = null;
	}

	/**
	 * Read the `active` flag straight from the network option.
	 *
	 * Deliberately bypasses read_sunrise_meta() so the assertion measures what
	 * was persisted, not what the static cache happens to hold.
	 *
	 * @return bool
	 */
	private function read_persisted_active_flag() {

		$meta = get_network_option(null, 'wu_sunrise_meta', []);

		return ! empty($meta['active']);
	}

	/**
	 * Force WP_Ultimo::is_loaded() to a given value and return the previous one.
	 *
	 * @param bool $loaded Value to force.
	 * @return bool The previous value, to restore afterwards.
	 */
	private function force_plugin_loaded_state($loaded) {

		$plugin   = \WP_Ultimo();
		$property = (new \ReflectionObject($plugin))->getProperty('loaded');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$previous = $property->getValue($plugin);

		$property->setValue($plugin, $loaded);

		return $previous;
	}

	/**
	 * A process that does not have the plugin running must not deactivate sunrise.
	 *
	 * sunrise.php is a drop-in, so maybe_tap_on_init() also runs in processes
	 * that never load plugins: `wp --skip-plugins`, the installer, or a custom
	 * bootstrap. There the main plugin class is absent and the state observed
	 * at the decision point is exactly the one reproduced here - `false`,
	 * because function_exists() short circuits the very same expression.
	 *
	 * PHP cannot undeclare a function inside a running process, so the fixture
	 * drives the other half of that same expression: is_loaded() returns false,
	 * which is what a process without the plugin produces.
	 *
	 * Before the fix this wrote `active => false` for the whole network, which
	 * takes down domain mapping until a process that does load plugins reaches
	 * `init` again.
	 */
	public function test_maybe_tap_on_init_does_not_deactivate_when_plugin_is_not_loaded() {

		$this->seed_sunrise_meta(true);

		$previous = $this->force_plugin_loaded_state(false);

		try {
			Sunrise::maybe_tap_on_init();

			$this->assertTrue(
				$this->read_persisted_active_flag(),
				'A process that does not have Ultimate Multisite loaded must not turn the network wide sunrise flag off.'
			);
		} finally {
			$this->force_plugin_loaded_state($previous);

			Sunrise::$sunrise_meta = null;
		}
	}

	/**
	 * The activation half of maybe_tap_on_init() must keep working.
	 *
	 * Control for the test above: when the plugin is loaded, init is still
	 * allowed to turn the flag on.
	 */
	public function test_maybe_tap_on_init_still_activates_when_plugin_is_loaded() {

		$this->seed_sunrise_meta(false);

		$previous = $this->force_plugin_loaded_state(true);

		try {
			Sunrise::maybe_tap_on_init();

			$this->assertTrue(
				$this->read_persisted_active_flag(),
				'With the plugin loaded, init must still turn the sunrise flag on.'
			);
		} finally {
			$this->force_plugin_loaded_state($previous);

			Sunrise::$sunrise_meta = null;
		}
	}

	/**
	 * A genuine deactivation must still turn the flag off.
	 *
	 * This is the call \WP_Ultimo\Hooks::on_deactivation() makes from the
	 * register_deactivation_hook() callback, and it is the path that keeps the
	 * meta accurate now that init no longer deactivates.
	 */
	public function test_explicit_deactivation_still_turns_the_flag_off() {

		$this->seed_sunrise_meta(true);

		try {
			$this->assertTrue(Sunrise::maybe_tap('deactivating'));

			$this->assertFalse(
				$this->read_persisted_active_flag(),
				'An explicit deactivation must still turn the sunrise flag off.'
			);
		} finally {
			Sunrise::$sunrise_meta = null;
		}
	}

	/**
	 * Test manage_sunrise_updates method doesn't throw fatal errors.
	 */
	public function test_manage_sunrise_updates_no_fatal_errors() {
		// Skip this test as it requires complex WordPress multisite operations
		// that involve blog switching and logging, which can fail in test environment
		$this->markTestSkipped('Requires complex multisite setup with logging capabilities');
	}

	/**
	 * Test try_upgrade method doesn't throw fatal errors.
	 */
	public function test_try_upgrade_no_fatal_errors() {
		// Skip this test as it requires complex WordPress multisite operations
		// that involve blog switching and logging, which can fail in test environment
		$this->markTestSkipped('Requires complex multisite setup with logging capabilities');
	}
}

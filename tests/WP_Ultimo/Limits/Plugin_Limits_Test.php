<?php

namespace WP_Ultimo\Limits;

/**
 * Tests for the Plugin_Limits class.
 */
class Plugin_Limits_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh Plugin_Limits instance via reflection.
	 *
	 * @return Plugin_Limits
	 */
	private function get_instance() {

		$ref      = new \ReflectionClass(Plugin_Limits::class);
		$instance = $ref->newInstanceWithoutConstructor();

		return $instance;
	}

	/**
	 * Test class exists.
	 */
	public function test_class_exists() {

		$this->assertTrue(class_exists(Plugin_Limits::class));
	}

	/**
	 * Test init method exists.
	 */
	public function test_init_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'init'));
	}

	/**
	 * Test load_limitations method exists.
	 */
	public function test_load_limitations_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'load_limitations'));
	}

	/**
	 * Test clear_plugin_list returns plugins on main site.
	 */
	public function test_clear_plugin_list_main_site() {

		$instance = $this->get_instance();

		$plugins = [
			'plugin1/plugin1.php' => [
				'Name'    => 'Plugin 1',
				'Network' => false,
			],
			'plugin2/plugin2.php' => [
				'Name'    => 'Plugin 2',
				'Network' => false,
			],
		];

		$result = $instance->clear_plugin_list($plugins);

		$this->assertSame($plugins, $result);
	}

	/**
	 * Test deactivate_network_plugins returns plugins on network admin.
	 */
	public function test_deactivate_network_plugins_network_admin() {

		$instance = $this->get_instance();

		$plugins = [
			'plugin1/plugin1.php' => time(),
			'plugin2/plugin2.php' => time(),
		];

		$result = $instance->deactivate_network_plugins($plugins);

		$this->assertSame($plugins, $result);
	}

	/**
	 * Test deactivate_plugins returns plugins on network admin.
	 */
	public function test_deactivate_plugins_network_admin() {

		$instance = $this->get_instance();

		$plugins = ['plugin1/plugin1.php', 'plugin2/plugin2.php'];

		$result = $instance->deactivate_plugins($plugins);

		$this->assertSame($plugins, $result);
	}

	/**
	 * Test clean_unused_shortcodes removes shortcodes.
	 */
	public function test_clean_unused_shortcodes() {

		$instance = $this->get_instance();

		$content = 'Some text [shortcode]content[/shortcode] more text';
		$result  = $instance->clean_unused_shortcodes($content);

		$this->assertStringNotContainsString('[shortcode]', $result);
		$this->assertStringNotContainsString('[/shortcode]', $result);
	}

	/**
	 * Test clean_unused_shortcodes with no shortcodes.
	 */
	public function test_clean_unused_shortcodes_no_shortcodes() {

		$instance = $this->get_instance();

		$content = 'Plain text without shortcodes';
		$result  = $instance->clean_unused_shortcodes($content);

		$this->assertSame($content, $result);
	}

	/**
	 * Test admin_page_hooks method exists.
	 */
	public function test_admin_page_hooks_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'admin_page_hooks'));
	}

	/**
	 * Test activate_and_inactive_plugins method exists.
	 */
	public function test_activate_and_inactive_plugins_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'activate_and_inactive_plugins'));
	}

	/**
	 * Test maybe_activate_and_inactive_plugins method exists.
	 */
	public function test_maybe_activate_and_inactive_plugins_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'maybe_activate_and_inactive_plugins'));
	}

	/**
	 * Test clear_actions method exists.
	 */
	public function test_clear_actions_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'clear_actions'));
	}

	/**
	 * Test clear_actions returns actions when function not available.
	 */
	public function test_clear_actions_returns_actions() {

		$instance = $this->get_instance();

		$actions = [
			'activate'   => 'Activate',
			'deactivate' => 'Deactivate',
		];

		$result = $instance->clear_actions($actions, 'plugin/plugin.php');

		$this->assertSame($actions, $result);
	}

	/**
	 * Test class uses Singleton trait.
	 */
	public function test_uses_singleton_trait() {

		$instance = $this->get_instance();

		$traits = class_uses($instance);

		$this->assertContains(\WP_Ultimo\Traits\Singleton::class, $traits);
	}

	/**
	 * Test plugins property starts as an empty per-site cache.
	 */
	public function test_plugins_property() {

		$instance = $this->get_instance();

		$value = $this->get_cache($instance, 'plugins');

		$this->assertSame([], $value);
	}

	/**
	 * Test network_plugins property starts as an empty per-site cache.
	 */
	public function test_network_plugins_property() {

		$instance = $this->get_instance();

		$value = $this->get_cache($instance, 'network_plugins');

		$this->assertSame([], $value);
	}

	/**
	 * Reads one of the protected caches.
	 *
	 * @param Plugin_Limits $instance The instance to read from.
	 * @param string        $property The cache property name.
	 * @return mixed
	 */
	private function get_cache($instance, $property) {

		$ref = new \ReflectionProperty($instance, $property);

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		return $ref->getValue($instance);
	}

	/**
	 * Creates a site for plugin cache tests.
	 *
	 * @return int
	 */
	private function create_test_site() {

		$site = wu_create_site(
			[
				'domain' => 'plugin-limits-' . wp_rand() . '.example.com',
			]
		);

		$this->assertNotWPError($site);

		return $site->get_id();
	}

	/**
	 * Test the site-level list of one site is never served to another site.
	 *
	 * The `option_active_plugins` filter stays attached across switch_to_blog(),
	 * and WordPress writes back what it reads through it, so a shared cache
	 * stores one site's active plugins inside another site.
	 */
	public function test_deactivate_plugins_is_not_shared_between_sites() {

		$instance = $this->get_instance();

		$site_a = $this->create_test_site();
		$site_b = $this->create_test_site();

		switch_to_blog($site_a);
		$result_a = $instance->deactivate_plugins(['site-a/site-a.php']);
		restore_current_blog();

		switch_to_blog($site_b);
		$result_b = $instance->deactivate_plugins(['site-b/site-b.php']);
		restore_current_blog();

		$this->assertContains('site-a/site-a.php', $result_a);
		$this->assertContains('site-b/site-b.php', $result_b);
		$this->assertNotContains('site-a/site-a.php', $result_b);
	}

	/**
	 * Test the network list of one site is never served to another site.
	 */
	public function test_deactivate_network_plugins_is_not_shared_between_sites() {

		$instance = $this->get_instance();

		$site_a = $this->create_test_site();
		$site_b = $this->create_test_site();

		switch_to_blog($site_a);
		$result_a = $instance->deactivate_network_plugins(['site-a/site-a.php' => 1]);
		restore_current_blog();

		switch_to_blog($site_b);
		$result_b = $instance->deactivate_network_plugins(['site-b/site-b.php' => 1]);
		restore_current_blog();

		$this->assertArrayHasKey('site-a/site-a.php', $result_a);
		$this->assertArrayHasKey('site-b/site-b.php', $result_b);
		$this->assertArrayNotHasKey('site-a/site-a.php', $result_b);
	}

	/**
	 * Test both caches are stored under the ID of the site they were built for.
	 */
	public function test_caches_are_keyed_by_blog_id() {

		$instance = $this->get_instance();

		$site_a = $this->create_test_site();
		$site_b = $this->create_test_site();

		foreach ([$site_a, $site_b] as $site_id) {
			switch_to_blog($site_id);

			$instance->deactivate_plugins(['some/some.php']);
			$instance->deactivate_network_plugins(['some/some.php' => 1]);

			restore_current_blog();
		}

		$this->assertSame([$site_a, $site_b], array_keys($this->get_cache($instance, 'plugins')));
		$this->assertSame([$site_a, $site_b], array_keys($this->get_cache($instance, 'network_plugins')));
	}

	/**
	 * Test the cache is still reused within a single site.
	 *
	 * Non-regression guard: making the cache per-site must not turn it off. The
	 * second call is answered from the cache, so its own argument is ignored.
	 */
	public function test_deactivate_plugins_still_caches_within_the_same_site() {

		$instance = $this->get_instance();

		$site_id = $this->create_test_site();

		switch_to_blog($site_id);

		$first  = $instance->deactivate_plugins(['first/first.php']);
		$second = $instance->deactivate_plugins(['second/second.php']);

		restore_current_blog();

		$this->assertSame($first, $second);
		$this->assertNotContains('second/second.php', $second);
	}

	/**
	 * Test the network cache is still reused within a single site.
	 */
	public function test_deactivate_network_plugins_still_caches_within_the_same_site() {

		$instance = $this->get_instance();

		$site_id = $this->create_test_site();

		switch_to_blog($site_id);

		$first  = $instance->deactivate_network_plugins(['first/first.php' => 1]);
		$second = $instance->deactivate_network_plugins(['second/second.php' => 1]);

		restore_current_blog();

		$this->assertSame($first, $second);
		$this->assertArrayNotHasKey('second/second.php', $second);
	}

	/**
	 * Test flush_plugin_caches empties both caches.
	 */
	public function test_flush_plugin_caches_empties_both_caches() {

		$instance = $this->get_instance();

		$site_id = $this->create_test_site();

		switch_to_blog($site_id);

		$instance->deactivate_plugins(['some/some.php']);
		$instance->deactivate_network_plugins(['some/some.php' => 1]);

		restore_current_blog();

		$this->assertNotSame([], $this->get_cache($instance, 'plugins'));
		$this->assertNotSame([], $this->get_cache($instance, 'network_plugins'));

		$instance->flush_plugin_caches();

		$this->assertSame([], $this->get_cache($instance, 'plugins'));
		$this->assertSame([], $this->get_cache($instance, 'network_plugins'));
	}
}

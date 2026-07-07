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
	 * Test plugins property exists.
	 */
	public function test_plugins_property() {

		$instance = $this->get_instance();

		$ref = new \ReflectionProperty($instance, 'plugins');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$value = $ref->getValue($instance);

		$this->assertNull($value);
	}

	/**
	 * Test network_plugins property exists.
	 */
	public function test_network_plugins_property() {

		$instance = $this->get_instance();

		$ref = new \ReflectionProperty($instance, 'network_plugins');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$value = $ref->getValue($instance);

		$this->assertNull($value);
	}
}

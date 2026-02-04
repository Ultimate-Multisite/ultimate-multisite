<?php

namespace WP_Ultimo\Helpers;

use WP_UnitTestCase;

class WP_Config_Test extends WP_UnitTestCase {

	/**
	 * @var WP_Config
	 */
	protected $wp_config;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->wp_config = WP_Config::get_instance();
	}

	/**
	 * Test get_instance returns singleton.
	 */
	public function test_get_instance_returns_singleton(): void {

		$instance1 = WP_Config::get_instance();
		$instance2 = WP_Config::get_instance();

		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test get_wp_config_path returns a string.
	 */
	public function test_get_wp_config_path_returns_string(): void {

		$path = $this->wp_config->get_wp_config_path();

		$this->assertIsString($path);
		$this->assertStringContainsString('.php', $path);
	}

	/**
	 * Test inject_contents inserts at correct position.
	 */
	public function test_inject_contents_inserts_at_position(): void {

		$content = ['line1', 'line2', 'line3'];

		$result = $this->wp_config->inject_contents($content, 1, 'inserted');

		$this->assertCount(4, $result);
		$this->assertEquals('line1', $result[0]);
		$this->assertEquals('inserted', $result[1]);
		$this->assertEquals('line2', $result[2]);
		$this->assertEquals('line3', $result[3]);
	}

	/**
	 * Test inject_contents at beginning.
	 */
	public function test_inject_contents_at_beginning(): void {

		$content = ['line1', 'line2'];

		$result = $this->wp_config->inject_contents($content, 0, 'first');

		$this->assertCount(3, $result);
		$this->assertEquals('first', $result[0]);
		$this->assertEquals('line1', $result[1]);
	}

	/**
	 * Test inject_contents at end.
	 */
	public function test_inject_contents_at_end(): void {

		$content = ['line1', 'line2'];

		$result = $this->wp_config->inject_contents($content, 2, 'last');

		$this->assertCount(3, $result);
		$this->assertEquals('last', $result[2]);
	}

	/**
	 * Test inject_contents with array value.
	 */
	public function test_inject_contents_with_array_value(): void {

		$content = ['line1', 'line3'];

		$result = $this->wp_config->inject_contents($content, 1, ['line2a', 'line2b']);

		$this->assertCount(4, $result);
		$this->assertEquals('line2a', $result[1]);
		$this->assertEquals('line2b', $result[2]);
	}

	/**
	 * Test find_injected_line finds existing constant.
	 */
	public function test_find_injected_line_finds_constant(): void {

		$config = [
			"<?php\n",
			"define( 'WP_DEBUG', false );\n",
			"define( 'WU_TEST_CONSTANT', 'test_value' ); // Automatically injected\n",
			"\$table_prefix = 'wp_';\n",
		];

		$result = $this->wp_config->find_injected_line($config, 'WU_TEST_CONSTANT');

		$this->assertIsArray($result);
		$this->assertEquals(2, $result[1]);
	}

	/**
	 * Test find_injected_line returns false for missing constant.
	 */
	public function test_find_injected_line_returns_false_for_missing(): void {

		$config = [
			"<?php\n",
			"define( 'WP_DEBUG', false );\n",
		];

		$result = $this->wp_config->find_injected_line($config, 'NONEXISTENT_CONSTANT');

		$this->assertFalse($result);
	}

	/**
	 * Test find_reference_hook_line finds table_prefix line.
	 */
	public function test_find_reference_hook_line_finds_table_prefix(): void {

		global $wpdb;

		$config = [
			"<?php\n",
			"define( 'DB_NAME', 'wordpress' );\n",
			"\$table_prefix = '{$wpdb->prefix}';\n",
			"require_once ABSPATH . 'wp-settings.php';\n",
		];

		$result = $this->wp_config->find_reference_hook_line($config);

		$this->assertIsInt($result);
		$this->assertEquals(2, $result);
	}

	/**
	 * Test find_reference_hook_line finds Happy Publishing comment.
	 */
	public function test_find_reference_hook_line_finds_happy_publishing(): void {

		$config = [
			"<?php\n",
			"define( 'DB_NAME', 'wordpress' );\n",
			"/* That's all, stop editing! Happy publishing. */\n",
			"require_once ABSPATH . 'wp-settings.php';\n",
		];

		$result = $this->wp_config->find_reference_hook_line($config);

		// The Happy Publishing pattern uses -2 offset
		$this->assertIsInt($result);
	}

	/**
	 * Test find_reference_hook_line finds php opening tag as fallback.
	 */
	public function test_find_reference_hook_line_finds_php_tag_fallback(): void {

		$config = [
			"<?php\n",
			"// Some custom config\n",
		];

		$result = $this->wp_config->find_reference_hook_line($config);

		$this->assertIsInt($result);
	}
}

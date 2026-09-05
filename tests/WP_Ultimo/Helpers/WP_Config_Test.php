<?php

namespace WP_Ultimo\Helpers;

use WP_UnitTestCase;

class WP_Config_Test extends WP_UnitTestCase {

	/**
	 * @var WP_Config
	 */
	protected $wp_config;

	/**
	 * @var string
	 */
	protected $config_path;

	/**
	 * @var callable
	 */
	protected $config_path_filter;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->wp_config = WP_Config::get_instance();
	}

	/**
	 * Remove temporary configuration files.
	 */
	public function tearDown(): void {

		if ($this->config_path_filter) {
			remove_filter('wu_wp_config_path', $this->config_path_filter);
		}

		if ($this->config_path && file_exists($this->config_path)) {
			wp_delete_file($this->config_path);
		}

		parent::tearDown();
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
	 * Test duplicate constants are replaced by one authoritative definition.
	 */
	public function test_inject_wp_config_constants_removes_duplicates(): void {

		$this->use_config_contents(
			"<?php\n" .
			"define( 'MULTISITE', false );\n" .
			"  define(\n\t'MULTISITE',\n\ttrue\n);\n" .
			"\$table_prefix = 'wp_';\n" .
			"/* That's all, stop editing! Happy publishing. */\n" .
			"require_once ABSPATH . 'wp-settings.php';\n"
		);

		$result = $this->wp_config->inject_wp_config_constants(
			[
				'MULTISITE'           => true,
				'DOMAIN_CURRENT_SITE' => "www.roberto's.example",
			]
		);

		$this->assertTrue($result);

		$contents = file_get_contents($this->config_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertSame(1, preg_match_all('/\bdefine\s*\(\s*[\'\"]MULTISITE[\'\"]/', $contents));
		$this->assertStringContainsString("define( 'MULTISITE', true );", $contents);
		$this->assertStringContainsString("define( 'DOMAIN_CURRENT_SITE', 'www.roberto\\'s.example' );", $contents);
	}

	/**
	 * Test table prefix is used when the standard WordPress comment is absent.
	 */
	public function test_inject_wp_config_constant_uses_table_prefix_fallback(): void {

		$this->use_config_contents(
			"<?php\r\n" .
			"\$table_prefix = 'wp_';\r\n" .
			"require_once ABSPATH . 'wp-settings.php';\r\n"
		);

		$result   = $this->wp_config->inject_wp_config_constant('SUNRISE', true);
		$contents = file_get_contents($this->config_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertTrue($result);
		$this->assertStringContainsString("\$table_prefix = 'wp_';\r\ndefine( 'SUNRISE', true );", $contents);
	}

	/**
	 * Test failed syntax validation leaves the original file untouched.
	 */
	public function test_inject_wp_config_constant_preserves_original_on_invalid_php(): void {

		$original = "<?php\ndefine( 'BROKEN', true;\n/* That's all, stop editing! Happy publishing. */\n";

		$this->use_config_contents($original);

		$result = $this->wp_config->inject_wp_config_constant('SUNRISE', true);

		$this->assertWPError($result);
		$this->assertSame($original, file_get_contents($this->config_path)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Test revert removes every ordinary definition of a constant.
	 */
	public function test_revert_removes_duplicate_definitions(): void {

		$this->use_config_contents(
			"<?php\n" .
			"define( 'SUNRISE', true );\n" .
			"defined( 'SUNRISE' ) || define( 'SUNRISE', false );\n" .
			"/* That's all, stop editing! Happy publishing. */\n"
		);

		$result   = $this->wp_config->revert('SUNRISE');
		$contents = file_get_contents($this->config_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertTrue($result);
		$this->assertStringNotContainsString("'SUNRISE'", $contents);
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

	/**
	 * Point WP_Config at a temporary fixture.
	 *
	 * @param string $contents Fixture contents.
	 */
	protected function use_config_contents($contents): void {

		$this->config_path = wp_tempnam('wp-config.php');
		file_put_contents($this->config_path, $contents); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->config_path_filter = fn() => $this->config_path;

		add_filter('wu_wp_config_path', $this->config_path_filter);
	}
}

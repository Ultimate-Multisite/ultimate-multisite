<?php

namespace WP_Ultimo\Helpers;

use WP_UnitTestCase;

class WP_Config_Test extends WP_UnitTestCase {

	/**
	 * @var WP_Config
	 */
	protected $wp_config;

	/**
	 * @var string[]
	 */
	protected $temporary_files = [];

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->wp_config = WP_Config::get_instance();
	}

	/**
	 * Clean up test fixtures.
	 */
	public function tearDown(): void {

		foreach ($this->temporary_files as $temporary_file) {
			unlink($temporary_file);
		}

		parent::tearDown();
	}

	/**
	 * Test injecting a constant does not create another definition.
	 */
	public function test_inject_wp_config_constant_does_not_create_duplicate(): void {

		$wp_config = $this->create_testable_wp_config(
			"<?php\n\n/* That's all, stop editing! Happy publishing. */\n"
		);

		$this->assertTrue($wp_config->inject_wp_config_constant('WU_TEST_CONSTANT', true));
		$this->assertTrue($wp_config->inject_wp_config_constant('WU_TEST_CONSTANT', false));

		$contents = file_get_contents($wp_config->get_wp_config_path());

		$this->assertSame(1, substr_count($contents, 'WU_TEST_CONSTANT'));
		$this->assertStringContainsString("define( 'WU_TEST_CONSTANT', false );", $contents);
	}

	/**
	 * Test updating a constant does not remove existing definitions.
	 */
	public function test_inject_wp_config_constant_does_not_remove_existing_definitions(): void {

		$wp_config = $this->create_testable_wp_config(
			"<?php\n\ndefine( 'WU_TEST_CONSTANT', false );\ndefine( 'WU_TEST_CONSTANT', true );\n\n/* That's all, stop editing! Happy publishing. */\n"
		);

		$this->assertTrue($wp_config->inject_wp_config_constant('WU_TEST_CONSTANT', false));

		$contents = file_get_contents($wp_config->get_wp_config_path());

		$this->assertSame(2, substr_count($contents, 'WU_TEST_CONSTANT'));
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
	 * Test reverting a constant delegates to WPConfigTransformer.
	 */
	public function test_revert_removes_constant(): void {

		$wp_config = $this->create_testable_wp_config(
			"<?php\n\ndefine( 'WU_TEST_CONSTANT', true );\n\n/* That's all, stop editing! Happy publishing. */\n"
		);

		$this->assertTrue($wp_config->revert('WU_TEST_CONSTANT'));

		$contents = file_get_contents($wp_config->get_wp_config_path());

		$this->assertStringNotContainsString('WU_TEST_CONSTANT', $contents);
	}

	/**
	 * Test inject_contents remains as a deprecated no-op.
	 */
	public function test_inject_contents_is_deprecated_noop(): void {

		$content = ['line1', 'line2', 'line3'];

		$this->setExpectedIncorrectUsage(WP_Config::class . '::inject_contents');

		$this->assertSame($content, $this->wp_config->inject_contents($content, 1, 'inserted'));
	}

	/**
	 * Test find_injected_line remains as a deprecated no-op.
	 */
	public function test_find_injected_line_is_deprecated_noop(): void {

		$this->setExpectedIncorrectUsage(WP_Config::class . '::find_injected_line');

		$this->assertFalse($this->wp_config->find_injected_line([], 'WU_TEST_CONSTANT'));
	}

	/**
	 * Test find_reference_hook_line remains as a deprecated no-op.
	 */
	public function test_find_reference_hook_line_is_deprecated_noop(): void {

		$this->setExpectedIncorrectUsage(WP_Config::class . '::find_reference_hook_line');

		$this->assertFalse($this->wp_config->find_reference_hook_line([]));
	}

	/**
	 * Create a WP_Config mock backed by a temporary wp-config.php file.
	 *
	 * @param string $contents Config file contents.
	 * @return WP_Config
	 */
	protected function create_testable_wp_config($contents) {

		$config_path = tempnam(sys_get_temp_dir(), 'wu-wp-config-');

		file_put_contents($config_path, $contents);

		$this->temporary_files[] = $config_path;

		$wp_config = $this->getMockBuilder(WP_Config::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_wp_config_path'])
			->getMock();

		$wp_config->method('get_wp_config_path')->willReturn($config_path);

		return $wp_config;
	}
}

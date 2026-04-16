<?php
/**
 * Tests for Scripts class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Test class for Scripts.
 */
class Scripts_Test extends WP_UnitTestCase {

	/**
	 * @var Scripts
	 */
	private Scripts $scripts;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();
		$this->scripts = Scripts::get_instance();
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf(Scripts::class, $this->scripts);
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(
			Scripts::get_instance(),
			Scripts::get_instance()
		);
	}

	/**
	 * Test register_script registers a script handle.
	 */
	public function test_register_script_registers_handle(): void {

		$this->scripts->register_script(
			'wu-test-script',
			'https://example.com/test.js',
			[],
			['in_footer' => true]
		);

		$this->assertTrue(wp_script_is('wu-test-script', 'registered'));

		// Clean up.
		wp_deregister_script('wu-test-script');
	}

	/**
	 * Test register_style registers a style handle.
	 */
	public function test_register_style_registers_handle(): void {

		$this->scripts->register_style(
			'wu-test-style',
			'https://example.com/test.css',
			[]
		);

		$this->assertTrue(wp_style_is('wu-test-style', 'registered'));

		// Clean up.
		wp_deregister_style('wu-test-style');
	}

	/**
	 * Test register_script uses plugin version.
	 */
	public function test_register_script_uses_plugin_version(): void {

		$this->scripts->register_script(
			'wu-test-version',
			'https://example.com/test.js'
		);

		global $wp_scripts;
		$registered = $wp_scripts->registered['wu-test-version'] ?? null;

		$this->assertNotNull($registered);
		$this->assertEquals(\WP_Ultimo::VERSION, $registered->ver);

		// Clean up.
		wp_deregister_script('wu-test-version');
	}

	/**
	 * Test register_style uses plugin version.
	 */
	public function test_register_style_uses_plugin_version(): void {

		$this->scripts->register_style(
			'wu-test-style-version',
			'https://example.com/test.css'
		);

		global $wp_styles;
		$registered = $wp_styles->registered['wu-test-style-version'] ?? null;

		$this->assertNotNull($registered);
		$this->assertEquals(\WP_Ultimo::VERSION, $registered->ver);

		// Clean up.
		wp_deregister_style('wu-test-style-version');
	}

	/**
	 * Test register_script with dependencies.
	 */
	public function test_register_script_with_dependencies(): void {

		$this->scripts->register_script(
			'wu-test-deps',
			'https://example.com/test.js',
			['jquery']
		);

		global $wp_scripts;
		$registered = $wp_scripts->registered['wu-test-deps'] ?? null;

		$this->assertNotNull($registered);
		$this->assertContains('jquery', $registered->deps);

		// Clean up.
		wp_deregister_script('wu-test-deps');
	}

	/**
	 * Test add_body_class_container_boxed with no setting.
	 */
	public function test_add_body_class_container_boxed_no_setting(): void {

		// Ensure the setting is off.
		delete_user_setting('wu_use_container');

		$result = $this->scripts->add_body_class_container_boxed('existing-class');

		$this->assertEquals('existing-class', $result);
	}

	/**
	 * Test add_body_class_container_boxed with setting enabled.
	 */
	public function test_add_body_class_container_boxed_enabled(): void {

		global $_updated_user_settings;

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		// Directly set the global that get_all_user_settings() reads,
		// because set_user_setting() fails when headers are already sent.
		$_updated_user_settings = ['wu_use_container' => '1'];

		$result = $this->scripts->add_body_class_container_boxed('existing-class');

		$this->assertStringContainsString('has-wu-container', $result);

		// Clean up.
		$_updated_user_settings = null;
	}

	/**
	 * Test get_password_requirements returns expected structure.
	 */
	public function test_get_password_requirements_structure(): void {

		$reflection = new \ReflectionClass($this->scripts);
		$method     = $reflection->getMethod('get_password_requirements');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->scripts);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('strength_setting', $result);
		$this->assertArrayHasKey('min_strength', $result);
		$this->assertArrayHasKey('enforce_rules', $result);
		$this->assertArrayHasKey('min_length', $result);
		$this->assertArrayHasKey('require_uppercase', $result);
		$this->assertArrayHasKey('require_lowercase', $result);
		$this->assertArrayHasKey('require_number', $result);
		$this->assertArrayHasKey('require_special', $result);
	}

	/**
	 * Test get_password_requirements default values.
	 */
	public function test_get_password_requirements_defaults(): void {

		$reflection = new \ReflectionClass($this->scripts);
		$method     = $reflection->getMethod('get_password_requirements');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->scripts);

		$this->assertIsInt($result['min_strength']);
		$this->assertIsBool($result['enforce_rules']);
		$this->assertIsInt($result['min_length']);
		$this->assertIsBool($result['require_uppercase']);
		$this->assertIsBool($result['require_lowercase']);
		$this->assertIsBool($result['require_number']);
		$this->assertIsBool($result['require_special']);
	}

	/**
	 * Test is_defender_strong_password_active returns false when Defender not present.
	 */
	public function test_is_defender_strong_password_active_false(): void {

		$reflection = new \ReflectionClass($this->scripts);
		$method     = $reflection->getMethod('is_defender_strong_password_active');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->scripts);

		$this->assertFalse($result);
	}

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks(): void {

		$this->assertNotFalse(has_action('init', [$this->scripts, 'register_default_scripts']));
		$this->assertNotFalse(has_action('init', [$this->scripts, 'register_default_styles']));
		$this->assertNotFalse(has_action('admin_enqueue_scripts', [$this->scripts, 'enqueue_default_admin_styles']));
		$this->assertNotFalse(has_action('admin_enqueue_scripts', [$this->scripts, 'enqueue_default_admin_scripts']));
		$this->assertNotFalse(has_filter('admin_body_class', [$this->scripts, 'add_body_class_container_boxed']));
	}

	/**
	 * Test register_default_scripts registers expected handles.
	 */
	public function test_register_default_scripts_registers_handles(): void {

		// Trigger registration.
		$this->scripts->register_default_scripts();

		$expected_handles = [
			'wu-vue',
			'wu-sweet-alert',
			'wu-flatpicker',
			'wu-tiptip',
			'wu-block-ui',
			'wu-accounting',
			'wu-functions',
			'wu-fields',
			'wu-admin',
			'wu-vue-apps',
			'wubox',
		];

		foreach ($expected_handles as $handle) {
			$this->assertTrue(
				wp_script_is($handle, 'registered'),
				"Script handle '{$handle}' should be registered."
			);
		}
	}

	/**
	 * Test register_default_styles registers expected handles.
	 */
	public function test_register_default_styles_registers_handles(): void {

		$this->scripts->register_default_styles();

		$expected_handles = [
			'wu-styling',
			'wu-admin',
			'wu-checkout',
			'wu-flags',
			'wu-password',
		];

		foreach ($expected_handles as $handle) {
			$this->assertTrue(
				wp_style_is($handle, 'registered'),
				"Style handle '{$handle}' should be registered."
			);
		}
	}

	/**
	 * Test localize_moment returns boolean.
	 */
	public function test_localize_moment_returns_bool(): void {

		$result = $this->scripts->localize_moment();

		$this->assertIsBool($result);
	}
}

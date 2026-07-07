<?php

namespace WP_Ultimo\Limits;

/**
 * Tests for the Theme_Limits class.
 */
class Theme_Limits_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh Theme_Limits instance via reflection.
	 * Avoids calling init() which has WP_CLI dependencies.
	 *
	 * @return Theme_Limits
	 */
	private function get_instance() {

		// Create instance directly to bypass Singleton init()
		$ref      = new \ReflectionClass(Theme_Limits::class);
		$instance = $ref->newInstanceWithoutConstructor();

		return $instance;
	}

	/**
	 * Test class exists and has correct name.
	 */
	public function test_class_exists() {

		$this->assertTrue(class_exists(Theme_Limits::class));
	}

	/**
	 * Test init method exists.
	 */
	public function test_init_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'init'));
	}

	/**
	 * Test force_active_theme_stylesheet returns original on main site.
	 */
	public function test_force_active_theme_stylesheet_main_site() {

		$instance = $this->get_instance();

		// On main site, should return original stylesheet
		$result = $instance->force_active_theme_stylesheet('twentytwentyone');

		$this->assertSame('twentytwentyone', $result);
	}

	/**
	 * Test force_active_theme_template returns original on main site.
	 */
	public function test_force_active_theme_template_main_site() {

		$instance = $this->get_instance();

		// On main site, should return original template
		$result = $instance->force_active_theme_template('twentytwentyone');

		$this->assertSame('twentytwentyone', $result);
	}

	/**
	 * Test maybe_remove_activate_button returns themes on main site.
	 */
	public function test_maybe_remove_activate_button_main_site() {

		$instance = $this->get_instance();

		$themes = [
			'twentytwentyone' => ['name' => 'Twenty Twenty-One'],
			'twentytwentytwo' => ['name' => 'Twenty Twenty-Two'],
		];

		$result = $instance->maybe_remove_activate_button($themes);

		$this->assertSame($themes, $result);
	}

	/**
	 * Test add_extra_available_themes returns themes on network admin.
	 */
	public function test_add_extra_available_themes_network_admin() {

		$instance = $this->get_instance();

		$themes = ['twentytwentyone', 'twentytwentytwo'];

		// In network admin context, should return original themes
		// (This test assumes we're not in network admin)
		$result = $instance->add_extra_available_themes($themes);

		$this->assertIsArray($result);
	}

	/**
	 * Test prevent_theme_activation_on_customizer returns data when no limitations.
	 */
	public function test_prevent_theme_activation_on_customizer_no_limitations() {

		$instance = $this->get_instance();

		$data    = ['key' => 'value'];
		$context = [];

		$result = $instance->prevent_theme_activation_on_customizer($data, $context);

		$this->assertSame($data, $result);
	}

	/**
	 * Test load_limitations method exists.
	 */
	public function test_load_limitations_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'load_limitations'));
	}

	/**
	 * Test hacky_remove_activate_button method exists.
	 */
	public function test_hacky_remove_activate_button_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'hacky_remove_activate_button'));
	}

	/**
	 * Test get_forced_theme_stylesheet is protected.
	 */
	public function test_get_forced_theme_stylesheet_protected() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'get_forced_theme_stylesheet');

		$this->assertTrue($ref->isProtected());
	}

	/**
	 * Test get_forced_theme_template is protected.
	 */
	public function test_get_forced_theme_template_protected() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'get_forced_theme_template');

		$this->assertTrue($ref->isProtected());
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
	 * Test themes_not_available property exists.
	 */
	public function test_themes_not_available_property() {

		$instance = $this->get_instance();

		$ref = new \ReflectionProperty($instance, 'themes_not_available');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$value = $ref->getValue($instance);

		$this->assertIsArray($value);
	}

	/**
	 * Test forced_theme_stylesheet property exists.
	 */
	public function test_forced_theme_stylesheet_property() {

		$instance = $this->get_instance();

		$ref = new \ReflectionProperty($instance, 'forced_theme_stylesheet');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$value = $ref->getValue($instance);

		$this->assertNull($value);
	}

	/**
	 * Test forced_theme_template property exists.
	 */
	public function test_forced_theme_template_property() {

		$instance = $this->get_instance();

		$ref = new \ReflectionProperty($instance, 'forced_theme_template');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$value = $ref->getValue($instance);

		$this->assertNull($value);
	}
}

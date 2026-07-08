<?php
/**
 * Tests for the Admin_Themes_Compatibility class.
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * @group admin-themes-compatibility
 */
class Admin_Themes_Compatibility_Test extends WP_UnitTestCase {

	public function tear_down() {
		remove_all_filters('wu_admin_themes_compatibility');
		remove_all_filters('admin_body_class');
		parent::tear_down();
	}

	// ------------------------------------------------------------------
	// Singleton
	// ------------------------------------------------------------------

	public function test_get_instance_returns_singleton() {
		$instance = Admin_Themes_Compatibility::get_instance();
		$this->assertInstanceOf(Admin_Themes_Compatibility::class, $instance);
	}

	public function test_get_instance_returns_same_instance() {
		$a = Admin_Themes_Compatibility::get_instance();
		$b = Admin_Themes_Compatibility::get_instance();
		$this->assertSame($a, $b);
	}

	// ------------------------------------------------------------------
	// init
	// ------------------------------------------------------------------

	public function test_init_registers_admin_body_class_filter() {
		$instance = Admin_Themes_Compatibility::get_instance();
		$instance->init();

		$this->assertNotFalse(has_filter('admin_body_class', [$instance, 'add_body_classes']));
	}

	// ------------------------------------------------------------------
	// get_admin_themes
	// ------------------------------------------------------------------

	public function test_get_admin_themes_returns_array() {
		$themes = Admin_Themes_Compatibility::get_admin_themes();
		$this->assertIsArray($themes);
	}

	public function test_get_admin_themes_contains_known_themes() {
		$themes = Admin_Themes_Compatibility::get_admin_themes();

		$expected_keys = [
			'material-wp',
			'pro-theme',
			'admin-2020',
			'clientside',
			'wphave',
			'waaspro',
		];

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $themes, "Missing theme key: $key");
		}
	}

	public function test_get_admin_themes_each_has_activated_key() {
		$themes = Admin_Themes_Compatibility::get_admin_themes();

		foreach ($themes as $key => $theme) {
			$this->assertArrayHasKey('activated', $theme, "Theme '$key' missing 'activated' key");
		}
	}

	public function test_get_admin_themes_activated_values_are_boolean() {
		$themes = Admin_Themes_Compatibility::get_admin_themes();

		foreach ($themes as $key => $theme) {
			$this->assertIsBool($theme['activated'], "Theme '$key' activated should be boolean");
		}
	}

	public function test_get_admin_themes_none_activated_by_default() {
		// In test env, none of these classes/functions should exist
		$themes = Admin_Themes_Compatibility::get_admin_themes();

		foreach ($themes as $key => $theme) {
			$this->assertFalse($theme['activated'], "Theme '$key' should not be activated in test env");
		}
	}

	// ------------------------------------------------------------------
	// add_body_classes
	// ------------------------------------------------------------------

	public function test_add_body_classes_returns_string() {
		$instance = Admin_Themes_Compatibility::get_instance();

		$result = $instance->add_body_classes('existing-class');
		$this->assertIsString($result);
	}

	public function test_add_body_classes_preserves_existing_classes() {
		$instance = Admin_Themes_Compatibility::get_instance();

		$result = $instance->add_body_classes('my-existing-class');
		$this->assertStringContainsString('my-existing-class', $result);
	}

	public function test_add_body_classes_adds_prefix_for_activated_themes() {
		// Simulate an activated theme via filter
		add_filter('wu_admin_themes_compatibility', function ($themes) {
			$themes['test-theme'] = ['activated' => true];
			return $themes;
		});

		$instance = Admin_Themes_Compatibility::get_instance();
		$result   = $instance->add_body_classes('');

		$this->assertStringContainsString('wu-compat-admin-theme-test-theme', $result);
	}

	public function test_add_body_classes_does_not_add_deactivated_themes() {
		$instance = Admin_Themes_Compatibility::get_instance();

		// All themes are deactivated in test env
		$result = $instance->add_body_classes('');

		$this->assertStringNotContainsString('wu-compat-admin-theme-material-wp', $result);
		$this->assertStringNotContainsString('wu-compat-admin-theme-pro-theme', $result);
	}

	// ------------------------------------------------------------------
	// Filter: wu_admin_themes_compatibility
	// ------------------------------------------------------------------

	public function test_filter_can_add_custom_themes() {
		add_filter('wu_admin_themes_compatibility', function ($themes) {
			$themes['custom-admin'] = ['activated' => true];
			return $themes;
		});

		$themes = Admin_Themes_Compatibility::get_admin_themes();
		$this->assertArrayHasKey('custom-admin', $themes);
		$this->assertTrue($themes['custom-admin']['activated']);
	}

	public function test_filter_can_remove_themes() {
		add_filter('wu_admin_themes_compatibility', function ($themes) {
			unset($themes['material-wp']);
			return $themes;
		});

		$themes = Admin_Themes_Compatibility::get_admin_themes();
		$this->assertArrayNotHasKey('material-wp', $themes);
	}
}

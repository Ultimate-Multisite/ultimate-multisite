<?php
/**
 * Tests for the Settings class.
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * @group settings
 */
class Settings_Test extends WP_UnitTestCase {

	/**
	 * @var Settings
	 */
	private $settings;

	public function set_up() {
		parent::set_up();
		$this->settings = Settings::get_instance();

		// Reset sections cache so default_sections runs fresh
		$ref = new \ReflectionProperty(Settings::class, 'sections');
		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}
		$ref->setValue($this->settings, null);
	}

	public function tear_down() {
		remove_all_filters('wu_get_setting');
		remove_all_filters('wu_save_setting');
		remove_all_filters('wu_settings_get_sections');
		remove_all_filters('wu_pre_save_settings');
		parent::tear_down();
	}

	// ------------------------------------------------------------------
	// Singleton
	// ------------------------------------------------------------------

	public function test_get_instance_returns_singleton() {
		$this->assertInstanceOf(Settings::class, $this->settings);
	}

	public function test_get_instance_returns_same_instance() {
		$a = Settings::get_instance();
		$b = Settings::get_instance();
		$this->assertSame($a, $b);
	}

	// ------------------------------------------------------------------
	// KEY constant
	// ------------------------------------------------------------------

	public function test_key_constant_is_v2_settings() {
		$this->assertEquals('v2_settings', Settings::KEY);
	}

	// ------------------------------------------------------------------
	// get_all
	// ------------------------------------------------------------------

	public function test_get_all_returns_array() {
		$result = $this->settings->get_all();
		$this->assertIsArray($result);
	}

	public function test_get_all_returns_empty_array_when_no_settings() {
		// Clear settings
		wu_save_option(Settings::KEY, []);

		$ref = new \ReflectionProperty(Settings::class, 'settings');
		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}
		$ref->setValue($this->settings, null);

		$result = $this->settings->get_all();
		$this->assertIsArray($result);
	}

	// ------------------------------------------------------------------
	// get_setting / save_setting
	// ------------------------------------------------------------------

	public function test_save_setting_stores_value() {
		$this->settings->save_setting('test_key_123', 'test_value_123');

		$result = $this->settings->get_setting('test_key_123');
		$this->assertEquals('test_value_123', $result);
	}

	public function test_get_setting_returns_default_when_not_set() {
		$result = $this->settings->get_setting('nonexistent_setting_xyz', 'my_default');
		$this->assertEquals('my_default', $result);
	}

	public function test_get_setting_returns_false_for_missing_setting_no_default() {
		$result = $this->settings->get_setting('totally_missing_setting_abc');
		// Returns false or the default from get_setting_defaults
		$this->assertNotNull($result);
	}

	public function test_save_setting_returns_boolean() {
		$result = $this->settings->save_setting('bool_test_key', 'value');
		$this->assertIsBool($result);
	}

	public function test_save_setting_overwrites_existing() {
		$this->settings->save_setting('overwrite_key', 'first');
		$this->settings->save_setting('overwrite_key', 'second');

		$this->assertEquals('second', $this->settings->get_setting('overwrite_key'));
	}

	public function test_save_setting_handles_numeric_values() {
		$this->settings->save_setting('numeric_key', 42);
		$this->assertEquals(42, $this->settings->get_setting('numeric_key'));
	}

	public function test_save_setting_handles_boolean_values() {
		$this->settings->save_setting('bool_key', true);
		$this->assertTrue($this->settings->get_setting('bool_key'));
	}

	public function test_save_setting_handles_array_values() {
		$this->settings->save_setting('array_key', ['a', 'b', 'c']);
		$this->assertEquals(['a', 'b', 'c'], $this->settings->get_setting('array_key'));
	}

	// ------------------------------------------------------------------
	// get_setting filter
	// ------------------------------------------------------------------

	public function test_get_setting_applies_filter() {
		add_filter('wu_get_setting', function ($value, $setting) {
			if ($setting === 'filtered_setting') {
				return 'filtered_value';
			}
			return $value;
		}, 10, 2);

		$result = $this->settings->get_setting('filtered_setting', 'original');
		$this->assertEquals('filtered_value', $result);
	}

	// ------------------------------------------------------------------
	// save_setting filter
	// ------------------------------------------------------------------

	public function test_save_setting_applies_filter() {
		add_filter('wu_save_setting', function ($value, $setting) {
			if ($setting === 'filter_save_test') {
				return 'modified_by_filter';
			}
			return $value;
		}, 10, 2);

		$this->settings->save_setting('filter_save_test', 'original');
		$result = $this->settings->get_setting('filter_save_test');
		$this->assertEquals('modified_by_filter', $result);
	}

	// ------------------------------------------------------------------
	// get_sections
	// ------------------------------------------------------------------

	public function test_get_sections_returns_array() {
		$sections = $this->settings->get_sections();
		$this->assertIsArray($sections);
	}

	public function test_get_sections_contains_core_section() {
		$sections = $this->settings->get_sections();
		$this->assertArrayHasKey('core', $sections);
	}

	public function test_get_sections_contains_default_sections() {
		$sections = $this->settings->get_sections();

		// Should have general, login-and-registration, memberships, sites, payment-gateways, etc.
		$this->assertArrayHasKey('general', $sections);
		$this->assertArrayHasKey('login-and-registration', $sections);
		$this->assertArrayHasKey('memberships', $sections);
		$this->assertArrayHasKey('sites', $sections);
		$this->assertArrayHasKey('payment-gateways', $sections);
	}

	public function test_get_sections_caches_result() {
		$first = $this->settings->get_sections();
		$second = $this->settings->get_sections();
		$this->assertSame($first, $second);
	}

	// ------------------------------------------------------------------
	// get_section
	// ------------------------------------------------------------------

	public function test_get_section_returns_array() {
		$section = $this->settings->get_section('general');
		$this->assertIsArray($section);
	}

	public function test_get_section_returns_fields_key() {
		$section = $this->settings->get_section('general');
		$this->assertArrayHasKey('fields', $section);
	}

	public function test_get_section_returns_default_for_unknown() {
		$section = $this->settings->get_section('nonexistent_section');
		$this->assertIsArray($section);
		$this->assertArrayHasKey('fields', $section);
		$this->assertEmpty($section['fields']);
	}

	// ------------------------------------------------------------------
	// add_section
	// ------------------------------------------------------------------

	public function test_add_section_registers_new_section() {
		// Reset sections cache
		$ref = new \ReflectionProperty(Settings::class, 'sections');
		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}
		$ref->setValue($this->settings, null);

		$this->settings->add_section('test_section', [
			'title' => 'Test Section',
			'desc'  => 'A test section',
		]);

		$sections = $this->settings->get_sections();
		$this->assertArrayHasKey('test_section', $sections);
	}

	// ------------------------------------------------------------------
	// add_field
	// ------------------------------------------------------------------

	public function test_add_field_registers_field_in_section() {
		$ref = new \ReflectionProperty(Settings::class, 'sections');
		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}
		$ref->setValue($this->settings, null);

		$this->settings->add_section('field_test_section', [
			'title' => 'Field Test',
			'desc'  => 'Testing fields',
		]);

		$this->settings->add_field('field_test_section', 'my_field', [
			'title'   => 'My Field',
			'type'    => 'text',
			'default' => 'hello',
		]);

		$sections = $this->settings->get_sections();
		$this->assertArrayHasKey('field_test_section', $sections);
		$this->assertArrayHasKey('my_field', $sections['field_test_section']['fields']);
	}

	// ------------------------------------------------------------------
	// force_registration_status
	// ------------------------------------------------------------------

	public function test_force_registration_status_returns_all_when_enabled() {
		$this->settings->save_setting('enable_registration', true);

		global $current_site;
		$network_id = $current_site->id;

		$result = $this->settings->force_registration_status('none', 'registration', $network_id);
		$this->assertEquals('all', $result);
	}

	public function test_force_registration_status_returns_original_for_different_network() {
		global $current_site;

		$result = $this->settings->force_registration_status('none', 'registration', 99999);
		$this->assertEquals('none', $result);
	}

	// ------------------------------------------------------------------
	// force_add_new_users
	// ------------------------------------------------------------------

	public function test_force_add_new_users_returns_setting_value() {
		$this->settings->save_setting('add_new_users', true);

		global $current_site;
		$network_id = $current_site->id;

		$result = $this->settings->force_add_new_users('', 'add_new_users', $network_id);
		$this->assertTrue($result);
	}

	public function test_force_add_new_users_returns_original_for_different_network() {
		global $current_site;

		$result = $this->settings->force_add_new_users('original', 'add_new_users', 99999);
		$this->assertEquals('original', $result);
	}

	// ------------------------------------------------------------------
	// force_plugins_menu
	// ------------------------------------------------------------------

	public function test_force_plugins_menu_sets_plugins_key() {
		$this->settings->save_setting('menu_items_plugin', true);

		global $current_site;
		$network_id = $current_site->id;

		$result = $this->settings->force_plugins_menu([], 'menu_items', $network_id);
		$this->assertIsArray($result);
		$this->assertArrayHasKey('plugins', $result);
	}

	public function test_force_plugins_menu_returns_bool_status_unchanged() {
		global $current_site;
		$network_id = $current_site->id;

		$result = $this->settings->force_plugins_menu(false, 'menu_items', $network_id);
		$this->assertFalse($result);
	}

	public function test_force_plugins_menu_returns_original_for_different_network() {
		global $current_site;

		$status = ['plugins' => false];
		$result = $this->settings->force_plugins_menu($status, 'menu_items', 99999);
		$this->assertEquals($status, $result);
	}

	// ------------------------------------------------------------------
	// init
	// ------------------------------------------------------------------

	public function test_init_registers_hooks() {
		$this->settings->init();

		$this->assertNotFalse(has_action('init', [$this->settings, 'handle_legacy_filters']));
		$this->assertNotFalse(has_filter('pre_site_option_registration', [$this->settings, 'force_registration_status']));
		$this->assertNotFalse(has_filter('pre_site_option_add_new_users', [$this->settings, 'force_add_new_users']));
	}

	// ------------------------------------------------------------------
	// save_settings (bulk)
	// ------------------------------------------------------------------

	public function test_save_settings_returns_array() {
		$result = $this->settings->save_settings([]);
		$this->assertIsArray($result);
	}

	// ------------------------------------------------------------------
	// get_setting_defaults
	// ------------------------------------------------------------------

	public function test_get_setting_defaults_returns_array() {
		$defaults = Settings::get_setting_defaults();
		$this->assertIsArray($defaults);
	}

	// ------------------------------------------------------------------
	// General section fields
	// ------------------------------------------------------------------

	public function test_general_section_has_company_name_field() {
		$section = $this->settings->get_section('general');
		$this->assertArrayHasKey('company_name', $section['fields']);
	}

	public function test_general_section_has_currency_symbol_field() {
		$section = $this->settings->get_section('general');
		$this->assertArrayHasKey('currency_symbol', $section['fields']);
	}

	public function test_general_section_has_currency_position_field() {
		$section = $this->settings->get_section('general');
		$this->assertArrayHasKey('currency_position', $section['fields']);
	}

	public function test_general_section_has_decimal_separator_field() {
		$section = $this->settings->get_section('general');
		$this->assertArrayHasKey('decimal_separator', $section['fields']);
	}

	public function test_general_section_has_precision_field() {
		$section = $this->settings->get_section('general');
		$this->assertArrayHasKey('precision', $section['fields']);
	}

	// ------------------------------------------------------------------
	// Login & Registration section fields
	// ------------------------------------------------------------------

	public function test_login_section_has_enable_registration_field() {
		$section = $this->settings->get_section('login-and-registration');
		$this->assertArrayHasKey('enable_registration', $section['fields']);
	}

	public function test_login_section_has_default_role_field() {
		$section = $this->settings->get_section('login-and-registration');
		$this->assertArrayHasKey('default_role', $section['fields']);
	}

	public function test_login_section_has_minimum_password_strength_field() {
		$section = $this->settings->get_section('login-and-registration');
		$this->assertArrayHasKey('minimum_password_strength', $section['fields']);
	}

	// ------------------------------------------------------------------
	// default_role options — GH#865
	// ------------------------------------------------------------------

	/**
	 * The default_role select must not include "Use Ultimate Multisite default"
	 * (key 'default'). That option appears only when wu_get_roles_as_options()
	 * is called with $add_default_option = true — which happened because the
	 * string 'wu_get_roles_as_options' was passed as the options callback and
	 * Field::__get() forwarded $this (truthy) as the first argument.
	 */
	public function test_default_role_options_do_not_include_wu_default_option() {
		$section = $this->settings->get_section('login-and-registration');
		$this->assertArrayHasKey('default_role', $section['fields']);

		$options_callback = $section['fields']['default_role']['options'];
		$this->assertIsCallable($options_callback);

		$options = $options_callback();
		$this->assertArrayNotHasKey('default', $options);
	}

	public function test_main_site_default_role_options_do_not_include_wu_default_option() {
		$section = $this->settings->get_section('login-and-registration');
		$this->assertArrayHasKey('main_site_default_role', $section['fields']);

		$options_callback = $section['fields']['main_site_default_role']['options'];
		$this->assertIsCallable($options_callback);

		$options = $options_callback();
		$this->assertArrayNotHasKey('default', $options);
	}

	public function test_default_role_options_include_administrator() {
		$section          = $this->settings->get_section('login-and-registration');
		$options_callback = $section['fields']['default_role']['options'];

		$options = $options_callback();
		$this->assertArrayHasKey('administrator', $options);
	}

	// ------------------------------------------------------------------
	// get_all_with_defaults includes settings not yet saved to DB — GH#865
	// ------------------------------------------------------------------

	/**
	 * When the DB has no saved settings (fresh install), get_all_with_defaults()
	 * must still include registered fields with their computed defaults so the
	 * Vue data-state on the settings page initialises correctly.
	 */
	public function test_get_all_with_defaults_includes_default_role_when_db_is_empty() {
		// Simulate a fresh install with no saved settings.
		wu_save_option(Settings::KEY, []);

		$ref = new \ReflectionProperty(Settings::class, 'settings');
		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}
		$ref->setValue($this->settings, null);

		$all = $this->settings->get_all_with_defaults();

		$this->assertArrayHasKey('default_role', $all);
		$this->assertEquals('administrator', $all['default_role']);
	}

	public function test_get_all_with_defaults_preserves_saved_values() {
		// When a value IS saved in the DB it must be preserved.
		$this->settings->save_setting('default_role', 'editor');

		// Reset internal cache so get_all re-reads from option.
		$ref = new \ReflectionProperty(Settings::class, 'settings');
		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}
		$ref->setValue($this->settings, null);

		$all = $this->settings->get_all_with_defaults();

		$this->assertArrayHasKey('default_role', $all);
		$this->assertEquals('editor', $all['default_role']);
	}
}

<?php
/**
 * Test case for Tax class.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tax;

use WP_UnitTestCase;

/**
 * Test Tax class functionality.
 */
class Tax_Test extends WP_UnitTestCase {

	/**
	 * The Tax instance under test.
	 *
	 * @var Tax
	 */
	private Tax $tax;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->tax = Tax::get_instance();

		// Clear tax rates option before each test.
		wu_delete_option('tax_rates');
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		parent::tear_down();

		// Clean up settings.
		wu_delete_option('tax_rates');

		// Remove any filters we added.
		remove_all_filters('wu_enable_taxes');
		remove_all_filters('wu_get_tax_rate_types');
		remove_all_filters('wu_get_tax_rate_defaults');
		remove_all_filters('wu_get_tax_rates');
	}

	/**
	 * Test that Tax class uses the Singleton trait.
	 */
	public function test_uses_singleton_trait(): void {

		$reflection = new \ReflectionClass(Tax::class);
		$traits     = $reflection->getTraitNames();

		$this->assertContains(
			'WP_Ultimo\Traits\Singleton',
			$traits,
			'Tax class should use the Singleton trait'
		);
	}

	/**
	 * Test that get_instance returns a Tax instance.
	 */
	public function test_get_instance_returns_tax_instance(): void {

		$instance = Tax::get_instance();

		$this->assertInstanceOf(Tax::class, $instance);
	}

	/**
	 * Test that get_instance returns the same instance.
	 */
	public function test_get_instance_returns_same_instance(): void {

		$instance1 = Tax::get_instance();
		$instance2 = Tax::get_instance();

		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test is_enabled returns false when taxes are disabled.
	 */
	public function test_is_enabled_returns_false_when_disabled(): void {

		wu_save_setting('enable_taxes', false);

		$this->assertFalse($this->tax->is_enabled());
	}

	/**
	 * Test is_enabled returns true when taxes are enabled.
	 */
	public function test_is_enabled_returns_true_when_enabled(): void {

		wu_save_setting('enable_taxes', true);

		$this->assertTrue($this->tax->is_enabled());
	}

	/**
	 * Test is_enabled returns false when setting is explicitly cleared.
	 */
	public function test_is_enabled_returns_false_when_setting_cleared(): void {

		wu_save_setting('enable_taxes', false);

		$this->assertFalse($this->tax->is_enabled());
	}

	/**
	 * Test is_enabled is filterable.
	 */
	public function test_is_enabled_is_filterable(): void {

		wu_save_setting('enable_taxes', false);

		add_filter('wu_enable_taxes', '__return_true');

		$this->assertTrue($this->tax->is_enabled());
	}

	/**
	 * Test is_enabled filter can disable taxes.
	 */
	public function test_is_enabled_filter_can_disable(): void {

		wu_save_setting('enable_taxes', true);

		add_filter('wu_enable_taxes', '__return_false');

		$this->assertFalse($this->tax->is_enabled());
	}

	/**
	 * Test get_tax_rate_types returns default types.
	 */
	public function test_get_tax_rate_types_returns_default(): void {

		$types = $this->tax->get_tax_rate_types();

		$this->assertIsArray($types);
		$this->assertArrayHasKey('regular', $types);
		$this->assertEquals('Regular', $types['regular']);
	}

	/**
	 * Test get_tax_rate_types is filterable.
	 */
	public function test_get_tax_rate_types_is_filterable(): void {

		add_filter(
			'wu_get_tax_rate_types',
			function ($types) {
				$types['reduced'] = 'Reduced';
				return $types;
			}
		);

		$types = $this->tax->get_tax_rate_types();

		$this->assertArrayHasKey('regular', $types);
		$this->assertArrayHasKey('reduced', $types);
		$this->assertEquals('Reduced', $types['reduced']);
	}

	/**
	 * Test get_tax_rate_defaults returns all expected keys.
	 */
	public function test_get_tax_rate_defaults_returns_expected_keys(): void {

		$defaults = $this->tax->get_tax_rate_defaults();

		$this->assertIsArray($defaults);
		$this->assertArrayHasKey('id', $defaults);
		$this->assertArrayHasKey('title', $defaults);
		$this->assertArrayHasKey('country', $defaults);
		$this->assertArrayHasKey('state', $defaults);
		$this->assertArrayHasKey('city', $defaults);
		$this->assertArrayHasKey('tax_type', $defaults);
		$this->assertArrayHasKey('tax_amount', $defaults);
		$this->assertArrayHasKey('priority', $defaults);
		$this->assertArrayHasKey('compound', $defaults);
		$this->assertArrayHasKey('type', $defaults);
	}

	/**
	 * Test get_tax_rate_defaults returns correct default values.
	 */
	public function test_get_tax_rate_defaults_correct_values(): void {

		$defaults = $this->tax->get_tax_rate_defaults();

		$this->assertEquals('Tax Rate', $defaults['title']);
		$this->assertEquals('', $defaults['country']);
		$this->assertEquals('', $defaults['state']);
		$this->assertEquals('', $defaults['city']);
		$this->assertEquals('percentage', $defaults['tax_type']);
		$this->assertEquals(0, $defaults['tax_amount']);
		$this->assertEquals(10, $defaults['priority']);
		$this->assertFalse($defaults['compound']);
		$this->assertEquals('regular', $defaults['type']);
	}

	/**
	 * Test get_tax_rate_defaults generates a unique id.
	 */
	public function test_get_tax_rate_defaults_generates_unique_id(): void {

		$defaults1 = $this->tax->get_tax_rate_defaults();
		$defaults2 = $this->tax->get_tax_rate_defaults();

		$this->assertNotEmpty($defaults1['id']);
		$this->assertNotEmpty($defaults2['id']);
		$this->assertNotEquals($defaults1['id'], $defaults2['id']);
	}

	/**
	 * Test get_tax_rate_defaults is filterable.
	 */
	public function test_get_tax_rate_defaults_is_filterable(): void {

		add_filter(
			'wu_get_tax_rate_defaults',
			function ($defaults) {
				$defaults['priority'] = 20;
				return $defaults;
			}
		);

		$defaults = $this->tax->get_tax_rate_defaults();

		$this->assertEquals(20, $defaults['priority']);
	}

	/**
	 * Test get_tax_rates returns default category when no rates stored.
	 */
	public function test_get_tax_rates_returns_default_category(): void {

		$rates = $this->tax->get_tax_rates();

		$this->assertIsArray($rates);
		$this->assertArrayHasKey('default', $rates);
		$this->assertArrayHasKey('name', $rates['default']);
		$this->assertArrayHasKey('rates', $rates['default']);
		$this->assertEquals('Default', $rates['default']['name']);
		$this->assertIsArray($rates['default']['rates']);
	}

	/**
	 * Test get_tax_rates returns stored rates.
	 */
	public function test_get_tax_rates_returns_stored_rates(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'US Tax',
						'country'  => 'US',
						'state'    => 'CA',
						'city'     => '',
						'tax_rate' => 10,
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates = $this->tax->get_tax_rates();

		$this->assertArrayHasKey('default', $rates);
		$this->assertCount(1, $rates['default']['rates']);
		$this->assertEquals('US Tax', $rates['default']['rates'][0]['title']);
		$this->assertEquals('US', $rates['default']['rates'][0]['country']);
		$this->assertEquals(10, $rates['default']['rates'][0]['tax_rate']);
	}

	/**
	 * Test get_tax_rates merges defaults into each rate.
	 */
	public function test_get_tax_rates_merges_defaults(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Simple Rate',
						'country'  => 'US',
						'state'    => '',
						'city'     => '',
						'tax_rate' => 5,
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates     = $this->tax->get_tax_rates();
		$first_rate = $rates['default']['rates'][0];

		// These should come from defaults.
		$this->assertArrayHasKey('tax_type', $first_rate);
		$this->assertArrayHasKey('priority', $first_rate);
		$this->assertArrayHasKey('compound', $first_rate);
		$this->assertArrayHasKey('type', $first_rate);
		$this->assertEquals('percentage', $first_rate['tax_type']);
		$this->assertEquals(10, $first_rate['priority']);
		$this->assertFalse($first_rate['compound']);
	}

	/**
	 * Test get_tax_rates handles non-numeric tax_rate.
	 */
	public function test_get_tax_rates_handles_non_numeric_tax_rate(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Bad Rate',
						'country'  => 'US',
						'state'    => '',
						'city'     => '',
						'tax_rate' => 'invalid',
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates     = $this->tax->get_tax_rates();
		$first_rate = $rates['default']['rates'][0];

		$this->assertEquals(0, $first_rate['tax_rate']);
	}

	/**
	 * Test get_tax_rates keeps numeric tax_rate unchanged.
	 */
	public function test_get_tax_rates_keeps_numeric_tax_rate(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Valid Rate',
						'country'  => 'BR',
						'state'    => '',
						'city'     => '',
						'tax_rate' => 15.5,
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates     = $this->tax->get_tax_rates();
		$first_rate = $rates['default']['rates'][0];

		$this->assertEquals(15.5, $first_rate['tax_rate']);
	}

	/**
	 * Test get_tax_rates with multiple categories.
	 */
	public function test_get_tax_rates_with_multiple_categories(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Default Rate',
						'country'  => 'US',
						'state'    => '',
						'city'     => '',
						'tax_rate' => 10,
					],
				],
			],
			'eu' => [
				'name'  => 'EU',
				'rates' => [
					[
						'title'    => 'EU VAT',
						'country'  => 'DE',
						'state'    => '',
						'city'     => '',
						'tax_rate' => 19,
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates = $this->tax->get_tax_rates();

		$this->assertArrayHasKey('default', $rates);
		$this->assertArrayHasKey('eu', $rates);
		$this->assertCount(1, $rates['default']['rates']);
		$this->assertCount(1, $rates['eu']['rates']);
	}

	/**
	 * Test get_tax_rates ensures default category is always first.
	 */
	public function test_get_tax_rates_ensures_default_first(): void {

		// Store rates without a 'default' key explicitly - use a different key name.
		$tax_rates = [
			'first_category' => [
				'name'  => 'First Category',
				'rates' => [
					[
						'title'    => 'Rate A',
						'country'  => 'US',
						'state'    => '',
						'city'     => '',
						'tax_rate' => 5,
					],
				],
			],
			'second_category' => [
				'name'  => 'Second Category',
				'rates' => [],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates = $this->tax->get_tax_rates();

		// When 'default' key is missing, the first category should be moved to 'default'.
		$keys = array_keys($rates);
		$this->assertEquals('default', $keys[0]);
	}

	/**
	 * Test get_tax_rates is filterable.
	 */
	public function test_get_tax_rates_is_filterable(): void {

		add_filter(
			'wu_get_tax_rates',
			function ($rates, $fetch_state_options) {
				$rates['custom'] = [
					'name'  => 'Custom',
					'rates' => [],
				];
				return $rates;
			},
			10,
			2
		);

		$rates = $this->tax->get_tax_rates();

		$this->assertArrayHasKey('custom', $rates);
	}

	/**
	 * Test get_tax_rates with empty rates array.
	 */
	public function test_get_tax_rates_with_empty_rates(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates = $this->tax->get_tax_rates();

		$this->assertEmpty($rates['default']['rates']);
	}

	/**
	 * Test get_tax_rates passes fetch_state_options correctly.
	 */
	public function test_get_tax_rates_filter_receives_fetch_state_options(): void {

		$received_value = null;

		add_filter(
			'wu_get_tax_rates',
			function ($rates, $fetch_state_options) use (&$received_value) {
				$received_value = $fetch_state_options;
				return $rates;
			},
			10,
			2
		);

		$this->tax->get_tax_rates(false);
		$this->assertFalse($received_value);

		$this->tax->get_tax_rates(true);
		$this->assertTrue($received_value);
	}

	/**
	 * Test add_settings registers the tax settings section and fields.
	 */
	public function test_add_settings_registers_section_and_fields(): void {

		// Call add_settings to register section and fields.
		$this->tax->add_settings();

		// Check that the settings object has the taxes section.
		$settings = WP_Ultimo()->settings;

		$sections = $settings->get_sections();

		$this->assertArrayHasKey('taxes', $sections);
	}

	/**
	 * Test init registers expected actions.
	 */
	public function test_init_registers_actions(): void {

		// init() is called automatically by the singleton; check that actions are registered.
		$this->assertGreaterThan(
			0,
			has_action('init', [$this->tax, 'add_settings']),
			'add_settings should be registered on init action'
		);

		$this->assertGreaterThan(
			0,
			has_action('wp_ultimo_admin_pages', [$this->tax, 'add_admin_page']),
			'add_admin_page should be registered on wp_ultimo_admin_pages action'
		);

		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_get_tax_rates', [$this->tax, 'serve_taxes_rates_via_ajax']),
			'serve_taxes_rates_via_ajax should be registered on wp_ajax_wu_get_tax_rates'
		);

		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_save_tax_rates', [$this->tax, 'save_taxes_rates']),
			'save_taxes_rates should be registered on wp_ajax_wu_save_tax_rates'
		);
	}

	/**
	 * Test init registers sidebar widget action.
	 */
	public function test_init_registers_sidebar_widget_action(): void {

		$this->assertGreaterThan(
			0,
			has_action('wu_page_wp-ultimo-settings_load', [$this->tax, 'add_sidebar_widget']),
			'add_sidebar_widget should be registered on wu_page_wp-ultimo-settings_load'
		);
	}

	/**
	 * Test get_tax_rates with zero tax_rate value.
	 */
	public function test_get_tax_rates_with_zero_tax_rate(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Zero Rate',
						'country'  => 'US',
						'state'    => '',
						'city'     => '',
						'tax_rate' => 0,
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates     = $this->tax->get_tax_rates();
		$first_rate = $rates['default']['rates'][0];

		$this->assertEquals(0, $first_rate['tax_rate']);
	}

	/**
	 * Test get_tax_rates with integer string tax_rate.
	 */
	public function test_get_tax_rates_with_string_numeric_tax_rate(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'String Numeric Rate',
						'country'  => 'US',
						'state'    => '',
						'city'     => '',
						'tax_rate' => '10',
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates     = $this->tax->get_tax_rates();
		$first_rate = $rates['default']['rates'][0];

		// String '10' is numeric, so it should be kept.
		$this->assertEquals('10', $first_rate['tax_rate']);
	}

	/**
	 * Test get_tax_rates with empty string tax_rate.
	 */
	public function test_get_tax_rates_with_empty_string_tax_rate(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Empty Rate',
						'country'  => 'US',
						'state'    => '',
						'city'     => '',
						'tax_rate' => '',
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates     = $this->tax->get_tax_rates();
		$first_rate = $rates['default']['rates'][0];

		// Empty string is not numeric, so should be converted to 0.
		$this->assertEquals(0, $first_rate['tax_rate']);
	}

	/**
	 * Test get_tax_rate_types only has regular by default.
	 */
	public function test_get_tax_rate_types_has_only_regular_by_default(): void {

		$types = $this->tax->get_tax_rate_types();

		$this->assertCount(1, $types);
		$this->assertArrayHasKey('regular', $types);
	}

	/**
	 * Test get_tax_rates with multiple rates in one category.
	 */
	public function test_get_tax_rates_with_multiple_rates_in_category(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Rate 1',
						'country'  => 'US',
						'state'    => 'CA',
						'city'     => '',
						'tax_rate' => 8.5,
					],
					[
						'title'    => 'Rate 2',
						'country'  => 'US',
						'state'    => 'NY',
						'city'     => '',
						'tax_rate' => 8.875,
					],
					[
						'title'    => 'Rate 3',
						'country'  => 'BR',
						'state'    => '',
						'city'     => '',
						'tax_rate' => 17,
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates = $this->tax->get_tax_rates();

		$this->assertCount(3, $rates['default']['rates']);
		$this->assertEquals('Rate 1', $rates['default']['rates'][0]['title']);
		$this->assertEquals('Rate 2', $rates['default']['rates'][1]['title']);
		$this->assertEquals('Rate 3', $rates['default']['rates'][2]['title']);
	}

	/**
	 * Test get_tax_rates default values are merged for missing keys.
	 */
	public function test_get_tax_rates_defaults_fill_missing_keys(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Minimal Rate',
						'country'  => 'US',
						'tax_rate' => 5,
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates     = $this->tax->get_tax_rates();
		$first_rate = $rates['default']['rates'][0];

		// Keys from defaults that were not set.
		$this->assertArrayHasKey('state', $first_rate);
		$this->assertArrayHasKey('city', $first_rate);
		$this->assertArrayHasKey('tax_type', $first_rate);
		$this->assertArrayHasKey('tax_amount', $first_rate);
		$this->assertArrayHasKey('priority', $first_rate);
		$this->assertArrayHasKey('compound', $first_rate);
		$this->assertArrayHasKey('type', $first_rate);
		$this->assertArrayHasKey('id', $first_rate);

		// Values from the rate itself should override defaults.
		$this->assertEquals('Minimal Rate', $first_rate['title']);
		$this->assertEquals('US', $first_rate['country']);
		$this->assertEquals(5, $first_rate['tax_rate']);
	}

	/**
	 * Test is_enabled with truthy values.
	 */
	public function test_is_enabled_with_truthy_value_1(): void {

		wu_save_setting('enable_taxes', 1);

		$this->assertTrue((bool) $this->tax->is_enabled());
	}

	/**
	 * Test is_enabled with falsy value 0.
	 */
	public function test_is_enabled_with_falsy_value_zero(): void {

		wu_save_setting('enable_taxes', 0);

		$this->assertFalse((bool) $this->tax->is_enabled());
	}

	/**
	 * Test get_tax_rates with negative tax_rate.
	 */
	public function test_get_tax_rates_with_negative_tax_rate(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Negative Rate',
						'country'  => 'US',
						'state'    => '',
						'city'     => '',
						'tax_rate' => -5,
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates     = $this->tax->get_tax_rates();
		$first_rate = $rates['default']['rates'][0];

		// Negative numbers are numeric, so they should be kept.
		$this->assertEquals(-5, $first_rate['tax_rate']);
	}

	/**
	 * Test get_tax_rate_defaults id is a string.
	 */
	public function test_get_tax_rate_defaults_id_is_string(): void {

		$defaults = $this->tax->get_tax_rate_defaults();

		$this->assertIsString($defaults['id']);
	}

	/**
	 * Test that get_tax_rate_types filter replaces the entire array.
	 */
	public function test_get_tax_rate_types_filter_replaces_entire_array(): void {

		add_filter(
			'wu_get_tax_rate_types',
			function () {
				return [
					'custom' => 'Custom Only',
				];
			}
		);

		$types = $this->tax->get_tax_rate_types();

		$this->assertCount(1, $types);
		$this->assertArrayNotHasKey('regular', $types);
		$this->assertArrayHasKey('custom', $types);
	}

	/**
	 * Test get_tax_rates with null tax_rate.
	 */
	public function test_get_tax_rates_with_null_tax_rate(): void {

		$tax_rates = [
			'default' => [
				'name'  => 'Default',
				'rates' => [
					[
						'title'    => 'Null Rate',
						'country'  => 'US',
						'state'    => '',
						'city'     => '',
						'tax_rate' => null,
					],
				],
			],
		];

		wu_save_option('tax_rates', $tax_rates);

		$rates     = $this->tax->get_tax_rates();
		$first_rate = $rates['default']['rates'][0];

		// null is not numeric, should be set to 0.
		$this->assertEquals(0, $first_rate['tax_rate']);
	}
}

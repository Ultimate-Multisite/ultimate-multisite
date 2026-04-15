<?php

namespace WP_Ultimo\Models;

use WP_UnitTestCase;

/**
 * Test class for Checkout Form model functionality.
 *
 * Tests checkout form creation, settings management, field handling,
 * template processing, country restrictions, and validation rules.
 */
class Checkout_Form_Test extends WP_UnitTestCase {

	/**
	 * Test checkout form creation with valid data.
	 */
	public function test_checkout_form_creation_with_valid_data(): void {
		$checkout_form = new Checkout_Form();
		$checkout_form->set_name('Test Checkout Form');
		$checkout_form->set_slug('test-checkout-form');
		$checkout_form->set_active(true);
		$checkout_form->set_custom_css('.test { color: red; }');
		$checkout_form->set_template('single-step');

		$this->assertEquals('Test Checkout Form', $checkout_form->get_name());
		$this->assertEquals('test-checkout-form', $checkout_form->get_slug());
		$this->assertTrue($checkout_form->is_active());
		$this->assertEquals('.test { color: red; }', $checkout_form->get_custom_css());
		$this->assertEquals('single-step', $checkout_form->get_template());
	}

	/**
	 * Test default active status.
	 */
	public function test_default_active_status(): void {
		$checkout_form = new Checkout_Form();
		$this->assertTrue($checkout_form->is_active());
	}

	/**
	 * Test active status setter and getter.
	 */
	public function test_active_status_functionality(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_active(false);
		$this->assertFalse($checkout_form->is_active());

		$checkout_form->set_active(true);
		$this->assertTrue($checkout_form->is_active());

		// Test with non-boolean values
		$checkout_form->set_active(1);
		$this->assertTrue($checkout_form->is_active());

		$checkout_form->set_active(0);
		$this->assertFalse($checkout_form->is_active());
	}

	/**
	 * Test settings functionality.
	 */
	public function test_settings_functionality(): void {
		$checkout_form = new Checkout_Form();

		// Test empty settings
		$this->assertEquals([], $checkout_form->get_settings());

		// Test setting settings as array
		$settings = [
			[
				'id'     => 'checkout',
				'name'   => 'Checkout Step',
				'fields' => [],
			],
		];

		$checkout_form->set_settings($settings);
		$this->assertEquals($settings, $checkout_form->get_settings());

		// Test setting settings as serialized string
		$serialized_settings = serialize($settings);
		$checkout_form->set_settings($serialized_settings);
		$this->assertEquals($settings, $checkout_form->get_settings());
	}

	/**
	 * Test allowed countries functionality.
	 */
	public function test_allowed_countries_functionality(): void {
		$checkout_form = new Checkout_Form();

		// Test no country restrictions
		$this->assertFalse($checkout_form->has_country_lock());

		// Test setting allowed countries
		$countries = ['US', 'CA', 'GB'];
		$checkout_form->set_allowed_countries($countries);

		$this->assertTrue($checkout_form->has_country_lock());
		$this->assertEquals($countries, $checkout_form->get_allowed_countries());

		// Test with serialized countries
		$serialized_countries = serialize($countries);
		$checkout_form->set_allowed_countries($serialized_countries);
		$this->assertEquals($countries, $checkout_form->get_allowed_countries());
	}

	/**
	 * Test thank you page functionality.
	 */
	public function test_thank_you_page_functionality(): void {
		$checkout_form = new Checkout_Form();

		// Test no thank you page set
		$this->assertFalse($checkout_form->has_thank_you_page());
		$this->assertEmpty($checkout_form->get_thank_you_page_id());

		// Test setting thank you page
		$page_id = self::factory()->post->create(['post_type' => 'page']);
		$checkout_form->set_thank_you_page_id($page_id);

		$this->assertEquals($page_id, $checkout_form->get_thank_you_page_id());
		$this->assertTrue($checkout_form->has_thank_you_page());
	}

	/**
	 * Test conversion snippets functionality.
	 */
	public function test_conversion_snippets_functionality(): void {
		$checkout_form = new Checkout_Form();

		// Test empty snippets
		$this->assertEmpty($checkout_form->get_conversion_snippets());

		// Test setting snippets
		$snippets = '<script>console.log("conversion");</script>';
		$checkout_form->set_conversion_snippets($snippets);
		$this->assertEquals($snippets, $checkout_form->get_conversion_snippets());
	}

	/**
	 * Test shortcode generation.
	 */
	public function test_shortcode_generation(): void {
		$checkout_form = new Checkout_Form();
		$checkout_form->set_slug('test-form');

		$expected_shortcode = '[wu_checkout slug="test-form"]';
		$this->assertEquals($expected_shortcode, $checkout_form->get_shortcode());
	}

	/**
	 * Test validation rules.
	 */
	public function test_validation_rules(): void {
		$checkout_form = new Checkout_Form();
		$rules         = $checkout_form->validation_rules();

		// Check that required validation rules exist
		$this->assertArrayHasKey('name', $rules);
		$this->assertArrayHasKey('slug', $rules);
		$this->assertArrayHasKey('active', $rules);
		$this->assertArrayHasKey('custom_css', $rules);
		$this->assertArrayHasKey('settings', $rules);
		$this->assertArrayHasKey('allowed_countries', $rules);
		$this->assertArrayHasKey('thank_you_page_id', $rules);
		$this->assertArrayHasKey('conversion_snippets', $rules);
		$this->assertArrayHasKey('template', $rules);

		// Check specific rule patterns
		$this->assertStringContainsString('required', $rules['name']);
		$this->assertStringContainsString('required', $rules['slug']);
		$this->assertStringContainsString('unique', $rules['slug']);
		$this->assertStringContainsString('min:3', $rules['slug']);
		$this->assertStringContainsString('required', $rules['active']);
		$this->assertStringContainsString('default:1', $rules['active']);
		$this->assertStringContainsString('checkout_steps', $rules['settings']);
		$this->assertStringContainsString('integer', $rules['thank_you_page_id']);
		$this->assertStringContainsString('in:blank,single-step,multi-step', $rules['template']);
	}

	/**
	 * Test step count functionality.
	 */
	public function test_step_count_functionality(): void {
		$checkout_form = new Checkout_Form();

		// Test empty settings
		$this->assertEquals(0, $checkout_form->get_step_count());

		// Test with steps
		$settings = [
			[
				'id'     => 'step1',
				'name'   => 'Step 1',
				'fields' => [],
			],
			[
				'id'     => 'step2',
				'name'   => 'Step 2',
				'fields' => [],
			],
			[
				'id'     => 'step3',
				'name'   => 'Step 3',
				'fields' => [],
			],
		];

		$checkout_form->set_settings($settings);
		$this->assertEquals(3, $checkout_form->get_step_count());
	}

	/**
	 * Test field count functionality.
	 */
	public function test_field_count_functionality(): void {
		$checkout_form = new Checkout_Form();

		// Test empty settings
		$this->assertEquals(0, $checkout_form->get_field_count());

		// Test with fields
		$settings = [
			[
				'id'     => 'checkout',
				'name'   => 'Checkout',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
					[
						'id'   => 'password',
						'type' => 'password',
					],
					[
						'id'   => 'submit',
						'type' => 'submit_button',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);
		$this->assertEquals(3, $checkout_form->get_field_count());
	}

	/**
	 * Test get_step functionality.
	 */
	public function test_get_step_functionality(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'name'   => 'Checkout Step',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		// Test getting existing step
		$step = $checkout_form->get_step('checkout');
		$this->assertIsArray($step);
		$this->assertEquals('checkout', $step['id']);
		$this->assertEquals('Checkout Step', $step['name']);
		$this->assertArrayHasKey('logged', $step);
		$this->assertArrayHasKey('fields', $step);

		// Test getting non-existing step
		$non_existing_step = $checkout_form->get_step('non-existing');
		$this->assertFalse($non_existing_step);
	}

	/**
	 * Test get_field functionality.
	 */
	public function test_get_field_functionality(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'name'   => 'Checkout Step',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
						'name' => 'Email Address',
					],
					[
						'id'   => 'password',
						'type' => 'password',
						'name' => 'Password',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		// Test getting existing field
		$field = $checkout_form->get_field('checkout', 'email');
		$this->assertIsArray($field);
		$this->assertEquals('email', $field['id']);
		$this->assertEquals('email', $field['type']);

		// Test getting non-existing field
		$non_existing_field = $checkout_form->get_field('checkout', 'non-existing');
		$this->assertFalse($non_existing_field);

		// Test getting field from non-existing step
		$field_from_non_existing_step = $checkout_form->get_field('non-existing', 'email');
		$this->assertFalse($field_from_non_existing_step);
	}

	/**
	 * Test get_all_fields functionality.
	 */
	public function test_get_all_fields_functionality(): void {
		$checkout_form = new Checkout_Form();

		// Test empty settings
		$this->assertEquals([], $checkout_form->get_all_fields());

		$settings = [
			[
				'id'     => 'step1',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
					[
						'id'   => 'password',
						'type' => 'password',
					],
				],
			],
			[
				'id'     => 'step2',
				'fields' => [
					[
						'id'   => 'site_title',
						'type' => 'site_title',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$all_fields = $checkout_form->get_all_fields();
		$this->assertCount(3, $all_fields);

		$field_ids = array_column($all_fields, 'id');
		$this->assertContains('email', $field_ids);
		$this->assertContains('password', $field_ids);
		$this->assertContains('site_title', $field_ids);
	}

	/**
	 * Test get_all_fields_by_type functionality.
	 */
	public function test_get_all_fields_by_type_functionality(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
					[
						'id'   => 'username',
						'type' => 'text',
					],
					[
						'id'   => 'password',
						'type' => 'password',
					],
					[
						'id'   => 'bio',
						'type' => 'text',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		// Test getting fields by single type
		$text_fields = $checkout_form->get_all_fields_by_type('text');
		$this->assertCount(2, $text_fields);

		// Test getting fields by multiple types
		$multiple_types_fields = $checkout_form->get_all_fields_by_type(['email', 'password']);
		$this->assertCount(2, $multiple_types_fields);
	}

	/**
	 * Test template functionality.
	 */
	public function test_template_functionality(): void {
		$checkout_form = new Checkout_Form();

		// Test setting template
		$checkout_form->set_template('multi-step');
		$this->assertEquals('multi-step', $checkout_form->get_template());

		$checkout_form->set_template('single-step');
		$this->assertEquals('single-step', $checkout_form->get_template());

		$checkout_form->set_template('blank');
		$this->assertEquals('blank', $checkout_form->get_template());
	}

	/**
	 * Test use_template functionality.
	 */
	public function test_use_template_functionality(): void {
		$checkout_form = new Checkout_Form();

		// Test using single-step template
		$checkout_form->use_template('single-step');
		$settings = $checkout_form->get_settings();
		$this->assertNotEmpty($settings);
		$this->assertIsArray($settings);

		// Should have at least one step
		$this->assertGreaterThan(0, count($settings));
		$this->assertArrayHasKey('id', $settings[0]);

		// Test using multi-step template
		$checkout_form->use_template('multi-step');
		$multi_step_settings = $checkout_form->get_settings();
		$this->assertNotEmpty($multi_step_settings);
		$this->assertIsArray($multi_step_settings);

		// Multi-step should have more steps than single-step
		$this->assertGreaterThan(count($settings), count($multi_step_settings));
	}

	/**
	 * Test query class property.
	 */
	public function test_query_class(): void {
		$checkout_form = new Checkout_Form();

		// Use reflection to access protected property
		$reflection           = new \ReflectionClass($checkout_form);
		$query_class_property = $reflection->getProperty('query_class');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$query_class_property->setAccessible(true);
		}

		$query_class = $query_class_property->getValue($checkout_form);

		$this->assertEquals(\WP_Ultimo\Database\Checkout_Forms\Checkout_Form_Query::class, $query_class);
	}

	/**
	 * Test static method finish_checkout_form_fields.
	 */
	public function test_finish_checkout_form_fields(): void {
		// Without payment, should return empty array
		$fields = Checkout_Form::finish_checkout_form_fields();
		$this->assertEquals([], $fields);
	}

	/**
	 * Test static method membership_change_form_fields.
	 */
	public function test_membership_change_form_fields(): void {
		// Without membership, should return empty array
		$fields = Checkout_Form::membership_change_form_fields();
		$this->assertEquals([], $fields);
	}

	/**
	 * Test static method add_new_site_form_fields.
	 */
	public function test_add_new_site_form_fields(): void {
		// Without membership, should return empty array
		$fields = Checkout_Form::add_new_site_form_fields();
		$this->assertEquals([], $fields);
	}

	/**
	 * Test steps_to_show property.
	 */
	public function test_steps_to_show_functionality(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
			[
				'id'     => 'guest_only',
				'logged' => 'guests_only',
				'fields' => [
					[
						'id'   => 'signup',
						'type' => 'text',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$steps_to_show = $checkout_form->get_steps_to_show();
		$this->assertIsArray($steps_to_show);
	}

	/**
	 * Test get_steps_to_show filters out logged_only steps for guests.
	 */
	public function test_get_steps_to_show_filters_logged_only_for_guests(): void {
		// Ensure no user is logged in.
		wp_set_current_user(0);

		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'always_step',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
			[
				'id'     => 'logged_only_step',
				'logged' => 'logged_only',
				'fields' => [
					[
						'id'   => 'profile_name',
						'type' => 'text',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$steps_to_show = $checkout_form->get_steps_to_show();

		// logged_only step should be filtered out since no user is logged in
		$step_ids = array_column($steps_to_show, 'id');
		$this->assertContains('always_step', $step_ids);
		$this->assertNotContains('logged_only_step', $step_ids);
	}

	/**
	 * Test get_steps_to_show shows guests_only steps for guests.
	 */
	public function test_get_steps_to_show_shows_guest_steps_for_guests(): void {
		wp_set_current_user(0);

		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'always_step',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'pricing',
						'type' => 'pricing_table',
					],
				],
			],
			[
				'id'     => 'guest_step',
				'logged' => 'guests_only',
				'fields' => [
					[
						'id'   => 'username',
						'type' => 'text',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$steps_to_show = $checkout_form->get_steps_to_show();

		$step_ids = array_column($steps_to_show, 'id');
		$this->assertContains('always_step', $step_ids);
		$this->assertContains('guest_step', $step_ids);
	}

	/**
	 * Test get_steps_to_show caches result.
	 */
	public function test_get_steps_to_show_caches_result(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$first_call  = $checkout_form->get_steps_to_show();
		$second_call = $checkout_form->get_steps_to_show();

		// Should return same result on subsequent calls (cached)
		$this->assertSame($first_call, $second_call);
	}

	/**
	 * Test get_steps_to_show merges hidden step fields into last visible step.
	 */
	public function test_get_steps_to_show_merges_hidden_fields_into_last_step(): void {
		wp_set_current_user(0);

		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'visible_step',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
			[
				'id'     => 'hidden_fields_step',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'hidden_1',
						'type' => 'hidden',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$steps_to_show = $checkout_form->get_steps_to_show();

		// The hidden_fields_step only has hidden fields, so it should not appear as its own step.
		// Its data fields should be merged into the last visible step.
		$step_ids = array_column($steps_to_show, 'id');
		$this->assertContains('visible_step', $step_ids);
		$this->assertNotContains('hidden_fields_step', $step_ids);
	}

	/**
	 * Test get_steps_to_show with step that has only non-data fields (submit_button, period_selection, steps).
	 */
	public function test_get_steps_to_show_with_only_non_data_fields(): void {
		wp_set_current_user(0);

		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'main_step',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
					[
						'id'   => 'submit',
						'type' => 'submit_button',
					],
				],
			],
			[
				'id'     => 'submit_only_step',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'another_submit',
						'type' => 'submit_button',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$steps_to_show = $checkout_form->get_steps_to_show();

		// The submit_only_step has only non-data fields (submit_button is filtered as non-data),
		// so after filtering data fields it becomes empty and should not be its own step.
		$step_ids = array_column($steps_to_show, 'id');
		$this->assertContains('main_step', $step_ids);
		$this->assertNotContains('submit_only_step', $step_ids);
	}

	/**
	 * Test get_step with to_show parameter.
	 */
	public function test_get_step_with_to_show_parameter(): void {
		wp_set_current_user(0);

		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'always_step',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
			[
				'id'     => 'logged_step',
				'logged' => 'logged_only',
				'fields' => [
					[
						'id'   => 'profile',
						'type' => 'text',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		// Without to_show, logged_step is accessible from all settings
		$step = $checkout_form->get_step('logged_step', false);
		$this->assertIsArray($step);
		$this->assertEquals('logged_step', $step['id']);

		// With to_show=true, logged_step should not be found (filtered out for guests)
		$step_to_show = $checkout_form->get_step('logged_step', true);
		$this->assertFalse($step_to_show);

		// always_step should still be accessible with to_show=true
		$always_step = $checkout_form->get_step('always_step', true);
		$this->assertIsArray($always_step);
		$this->assertEquals('always_step', $always_step['id']);
	}

	/**
	 * Test get_step default values parsed via wp_parse_args.
	 */
	public function test_get_step_parses_default_values(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'   => 'minimal_step',
				'name' => 'Minimal Step',
			],
		];

		$checkout_form->set_settings($settings);

		$step = $checkout_form->get_step('minimal_step');
		$this->assertIsArray($step);
		$this->assertEquals('always', $step['logged']);
		$this->assertEquals([], $step['fields']);
	}

	/**
	 * Test allowed countries with empty array returns no lock.
	 */
	public function test_has_country_lock_with_empty_array(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_allowed_countries([]);
		$this->assertFalse($checkout_form->has_country_lock());
	}

	/**
	 * Test allowed countries with single country.
	 */
	public function test_allowed_countries_with_single_country(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_allowed_countries(['BR']);
		$this->assertTrue($checkout_form->has_country_lock());
		$this->assertEquals(['BR'], $checkout_form->get_allowed_countries());
	}

	/**
	 * Test has_thank_you_page returns false for non-existent page.
	 */
	public function test_has_thank_you_page_with_non_existent_page(): void {
		$checkout_form = new Checkout_Form();

		// Set a page ID that does not correspond to any post
		$checkout_form->set_thank_you_page_id(999999);

		$this->assertEquals(999999, $checkout_form->get_thank_you_page_id());
		$this->assertFalse($checkout_form->has_thank_you_page());
	}

	/**
	 * Test has_thank_you_page returns false for zero.
	 */
	public function test_has_thank_you_page_with_zero(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_thank_you_page_id(0);

		$this->assertFalse($checkout_form->has_thank_you_page());
	}

	/**
	 * Test set_thank_you_page_id stores value in meta array.
	 */
	public function test_set_thank_you_page_id_stores_meta(): void {
		$checkout_form = new Checkout_Form();

		$page_id = self::factory()->post->create(['post_type' => 'page']);
		$checkout_form->set_thank_you_page_id($page_id);

		$this->assertArrayHasKey(Checkout_Form::META_THANK_YOU_PAGE_ID, $checkout_form->meta);
		$this->assertEquals($page_id, $checkout_form->meta[ Checkout_Form::META_THANK_YOU_PAGE_ID ]);
	}

	/**
	 * Test set_conversion_snippets stores value in meta array.
	 */
	public function test_set_conversion_snippets_stores_meta(): void {
		$checkout_form = new Checkout_Form();

		$snippets = '<!-- GA tracking --><script>ga("send","event");</script>';
		$checkout_form->set_conversion_snippets($snippets);

		$this->assertArrayHasKey(Checkout_Form::META_CONVERSION_SNIPPETS, $checkout_form->meta);
		$this->assertEquals($snippets, $checkout_form->meta[ Checkout_Form::META_CONVERSION_SNIPPETS ]);
	}

	/**
	 * Test conversion snippets with empty string.
	 */
	public function test_conversion_snippets_with_empty_string(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_conversion_snippets('');
		$this->assertEquals('', $checkout_form->get_conversion_snippets());
	}

	/**
	 * Test shortcode with empty slug.
	 */
	public function test_shortcode_with_empty_slug(): void {
		$checkout_form = new Checkout_Form();

		$expected = '[wu_checkout slug=""]';
		$this->assertEquals($expected, $checkout_form->get_shortcode());
	}

	/**
	 * Test shortcode with special characters in slug.
	 */
	public function test_shortcode_with_special_slug(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_slug('my-custom-form-2024');
		$expected = '[wu_checkout slug="my-custom-form-2024"]';
		$this->assertEquals($expected, $checkout_form->get_shortcode());
	}

	/**
	 * Test custom CSS with empty string.
	 */
	public function test_custom_css_empty(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_custom_css('');
		$this->assertEquals('', $checkout_form->get_custom_css());
	}

	/**
	 * Test custom CSS with complex CSS.
	 */
	public function test_custom_css_complex(): void {
		$checkout_form = new Checkout_Form();

		$css = ".wu-form { margin: 10px; }\n.wu-form .field { padding: 5px; border: 1px solid #ccc; }";
		$checkout_form->set_custom_css($css);
		$this->assertEquals($css, $checkout_form->get_custom_css());
	}

	/**
	 * Test name setter and getter with various string types.
	 */
	public function test_name_with_various_strings(): void {
		$checkout_form = new Checkout_Form();

		// Empty name
		$checkout_form->set_name('');
		$this->assertEquals('', $checkout_form->get_name());

		// Name with special characters
		$checkout_form->set_name('Checkout Form #1 - Special & "Quoted"');
		$this->assertEquals('Checkout Form #1 - Special & "Quoted"', $checkout_form->get_name());

		// Name with unicode
		$checkout_form->set_name('Formulaire de paiement');
		$this->assertEquals('Formulaire de paiement', $checkout_form->get_name());
	}

	/**
	 * Test slug setter and getter.
	 */
	public function test_slug_with_various_strings(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_slug('');
		$this->assertEquals('', $checkout_form->get_slug());

		$checkout_form->set_slug('a-very-long-slug-with-many-parts');
		$this->assertEquals('a-very-long-slug-with-many-parts', $checkout_form->get_slug());
	}

	/**
	 * Test use_template with blank template.
	 */
	public function test_use_template_blank(): void {
		$checkout_form = new Checkout_Form();

		// First set some settings
		$checkout_form->set_settings([
			[
				'id'     => 'step1',
				'fields' => [['id' => 'field1', 'type' => 'text']],
			],
		]);

		// Using 'blank' template (not 'single-step' or 'multi-step') should set empty settings
		$checkout_form->use_template('blank');
		$settings = $checkout_form->get_settings();

		// Blank template doesn't match 'multi-step' or 'single-step' branches,
		// so $fields stays as empty array and gets set to settings
		$this->assertEquals([], $settings);
	}

	/**
	 * Test single-step template structure.
	 */
	public function test_single_step_template_has_correct_structure(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('single-step');
		$settings = $checkout_form->get_settings();

		// Single step should have exactly 1 step
		$this->assertCount(1, $settings);
		$this->assertEquals('checkout', $settings[0]['id']);

		// Check that the step has fields
		$this->assertNotEmpty($settings[0]['fields']);
		$this->assertIsArray($settings[0]['fields']);

		// Verify expected field types exist in the single step
		$field_types = array_column($settings[0]['fields'], 'type');
		$this->assertContains('email', $field_types);
		$this->assertContains('username', $field_types);
		$this->assertContains('password', $field_types);
		$this->assertContains('site_title', $field_types);
		$this->assertContains('site_url', $field_types);
		$this->assertContains('order_summary', $field_types);
		$this->assertContains('payment', $field_types);
		$this->assertContains('submit_button', $field_types);
	}

	/**
	 * Test multi-step template structure.
	 */
	public function test_multi_step_template_has_correct_structure(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('multi-step');
		$settings = $checkout_form->get_settings();

		// Multi-step should have 4 steps
		$this->assertCount(4, $settings);

		$step_ids = array_column($settings, 'id');
		$this->assertContains('checkout', $step_ids);
		$this->assertContains('site', $step_ids);
		$this->assertContains('user', $step_ids);
		$this->assertContains('payment', $step_ids);

		// User step should be guests_only
		$user_step_key = array_search('user', $step_ids, true);
		$this->assertEquals('guests_only', $settings[ $user_step_key ]['logged']);
	}

	/**
	 * Test get_all_fields with steps that have no fields key.
	 */
	public function test_get_all_fields_with_missing_fields_key(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'   => 'step1',
				'name' => 'Step 1',
			],
			[
				'id'   => 'step2',
				'name' => 'Step 2',
			],
		];

		$checkout_form->set_settings($settings);

		$all_fields = $checkout_form->get_all_fields();
		$this->assertEquals([], $all_fields);
	}

	/**
	 * Test get_all_fields_by_type returns empty array for non-matching type.
	 */
	public function test_get_all_fields_by_type_returns_empty_for_no_match(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$result = $checkout_form->get_all_fields_by_type('nonexistent_type');
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Test get_all_meta_fields method.
	 */
	public function test_get_all_meta_fields(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'fields' => [
					[
						'id'      => 'custom_text',
						'type'    => 'text',
						'save_as' => 'customer_meta',
					],
					[
						'id'      => 'custom_select',
						'type'    => 'select',
						'save_as' => 'customer_meta',
					],
					[
						'id'      => 'site_meta_field',
						'type'    => 'text',
						'save_as' => 'site_meta',
					],
					[
						'id'   => 'email',
						'type' => 'email',
					],
					[
						'id'      => 'custom_color',
						'type'    => 'color',
						'save_as' => 'customer_meta',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		// Default meta_type is 'customer_meta'
		$meta_fields = $checkout_form->get_all_meta_fields();

		// Should return text, select, and color fields with save_as = customer_meta
		$field_ids = array_column($meta_fields, 'id');
		$this->assertContains('custom_text', $field_ids);
		$this->assertContains('custom_select', $field_ids);
		$this->assertContains('custom_color', $field_ids);
		$this->assertNotContains('email', $field_ids);
		$this->assertNotContains('site_meta_field', $field_ids);
	}

	/**
	 * Test get_all_meta_fields with site_meta type.
	 */
	public function test_get_all_meta_fields_site_meta(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'fields' => [
					[
						'id'      => 'custom_text',
						'type'    => 'text',
						'save_as' => 'customer_meta',
					],
					[
						'id'      => 'site_field',
						'type'    => 'textarea',
						'save_as' => 'site_meta',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$site_meta_fields = $checkout_form->get_all_meta_fields('site_meta');

		$field_ids = array_column($site_meta_fields, 'id');
		$this->assertContains('site_field', $field_ids);
		$this->assertNotContains('custom_text', $field_ids);
	}

	/**
	 * Test get_all_meta_fields returns empty array when no meta fields exist.
	 */
	public function test_get_all_meta_fields_returns_empty_when_no_meta_fields(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$meta_fields = $checkout_form->get_all_meta_fields();
		$this->assertEmpty($meta_fields);
	}

	/**
	 * Test field count across multiple steps.
	 */
	public function test_field_count_across_multiple_steps(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'step1',
				'fields' => [
					['id' => 'f1', 'type' => 'email'],
					['id' => 'f2', 'type' => 'text'],
				],
			],
			[
				'id'     => 'step2',
				'fields' => [
					['id' => 'f3', 'type' => 'password'],
					['id' => 'f4', 'type' => 'site_title'],
					['id' => 'f5', 'type' => 'submit_button'],
				],
			],
			[
				'id'     => 'step3',
				'fields' => [
					['id' => 'f6', 'type' => 'payment'],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$this->assertEquals(6, $checkout_form->get_field_count());
		$this->assertEquals(3, $checkout_form->get_step_count());
	}

	/**
	 * Test get_field retrieves correct field from multi-step form.
	 */
	public function test_get_field_from_specific_step_in_multi_step(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'step1',
				'fields' => [
					['id' => 'email', 'type' => 'email', 'name' => 'Email'],
				],
			],
			[
				'id'     => 'step2',
				'fields' => [
					['id' => 'site_title', 'type' => 'site_title', 'name' => 'Title'],
					['id' => 'site_url', 'type' => 'site_url', 'name' => 'URL'],
				],
			],
		];

		$checkout_form->set_settings($settings);

		// Get field from step2
		$field = $checkout_form->get_field('step2', 'site_url');
		$this->assertIsArray($field);
		$this->assertEquals('site_url', $field['id']);
		$this->assertEquals('site_url', $field['type']);

		// Ensure field from step1 is not returned for step2
		$wrong_field = $checkout_form->get_field('step2', 'email');
		$this->assertFalse($wrong_field);
	}

	/**
	 * Test convert_steps_to_v2 with minimal step data.
	 */
	public function test_convert_steps_to_v2_basic(): void {
		$old_steps = [
			'begin-signup'  => [
				'name'   => 'Begin Signup',
				'fields' => [],
			],
			'create-account' => [
				'name'   => 'Create Account',
				'fields' => [],
			],
			'account' => [
				'name'   => 'Account Info',
				'fields' => [
					'submit' => [
						'name' => 'Submit',
						'type' => 'submit',
					],
				],
			],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps);

		$this->assertIsArray($result);

		// begin-signup and create-account should be excluded
		$step_ids = array_column($result, 'id');
		$this->assertNotContains('begin-signup', $step_ids);
		$this->assertNotContains('create-account', $step_ids);

		// account step should be converted
		$this->assertContains('account', $step_ids);

		// A payment step should always be appended
		$this->assertContains('payment', $step_ids);
	}

	/**
	 * Test convert_steps_to_v2 with user field conversions.
	 */
	public function test_convert_steps_to_v2_field_conversions(): void {
		$old_steps = [
			'account' => [
				'name'   => 'Account',
				'fields' => [
					'user_name' => [
						'name' => 'Username',
						'type' => 'text',
					],
					'user_email' => [
						'name' => 'Email',
						'type' => 'email',
					],
					'user_pass' => [
						'name' => 'Password',
						'type' => 'password',
					],
					'user_pass_conf' => [
						'name' => 'Confirm Password',
						'type' => 'password',
					],
					'blog_title' => [
						'name' => 'Blog Title',
						'type' => 'text',
					],
					'blogname' => [
						'name' => 'Blog URL',
						'type' => 'text',
					],
				],
			],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps);

		// Find the 'account' step
		$account_step = null;
		foreach ($result as $step) {
			if ('account' === $step['id']) {
				$account_step = $step;
				break;
			}
		}

		$this->assertNotNull($account_step);

		$field_ids = array_column($account_step['fields'], 'id');

		// user_pass_conf should be skipped
		$this->assertNotContains('user_pass_conf', $field_ids);

		// Check field type conversions
		$fields_by_original_id = [];
		foreach ($account_step['fields'] as $field) {
			$fields_by_original_id[ $field['id'] ] = $field;
		}

		// user_name should be converted to 'username' type
		$this->assertArrayHasKey('user_name', $fields_by_original_id);
		$this->assertEquals('username', $fields_by_original_id['user_name']['type']);

		// user_email should keep email type but get display_notices added
		$this->assertArrayHasKey('user_email', $fields_by_original_id);
		$this->assertFalse($fields_by_original_id['user_email']['display_notices']);

		// user_pass should become password type with password fields
		$this->assertArrayHasKey('user_pass', $fields_by_original_id);
		$this->assertEquals('password', $fields_by_original_id['user_pass']['type']);
		$this->assertTrue($fields_by_original_id['user_pass']['password_confirm_field']);

		// blog_title should become site_title type
		$this->assertArrayHasKey('blog_title', $fields_by_original_id);
		$this->assertEquals('site_title', $fields_by_original_id['blog_title']['type']);

		// blogname should become site_url type
		$this->assertArrayHasKey('blogname', $fields_by_original_id);
		$this->assertEquals('site_url', $fields_by_original_id['blogname']['type']);
		$this->assertTrue($fields_by_original_id['blogname']['display_url_preview']);
		$this->assertTrue($fields_by_original_id['blogname']['required']);
	}

	/**
	 * Test convert_steps_to_v2 skips url_preview and site_url fields.
	 */
	public function test_convert_steps_to_v2_skips_special_fields(): void {
		$old_steps = [
			'account' => [
				'name'   => 'Account',
				'fields' => [
					'url_preview' => [
						'name' => 'URL Preview',
						'type' => 'text',
					],
					'site_url' => [
						'name' => 'Site URL',
						'type' => 'text',
					],
					'user_name' => [
						'name' => 'Username',
						'type' => 'text',
					],
				],
			],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps);

		$account_step = null;
		foreach ($result as $step) {
			if ('account' === $step['id']) {
				$account_step = $step;
				break;
			}
		}

		$this->assertNotNull($account_step);

		$field_ids = array_column($account_step['fields'], 'id');

		// url_preview and site_url (honeypot) should be skipped
		$this->assertNotContains('url_preview', $field_ids);
		$this->assertNotContains('site_url', $field_ids);

		// user_name should still be present
		$this->assertContains('user_name', $field_ids);
	}

	/**
	 * Test convert_steps_to_v2 removes handler, view, hidden keys.
	 */
	public function test_convert_steps_to_v2_removes_unnecessary_keys(): void {
		$old_steps = [
			'account' => [
				'name'    => 'Account',
				'handler' => 'some_handler',
				'view'    => 'some_view',
				'hidden'  => true,
				'fields'  => [
					'user_name' => [
						'name' => 'Username',
						'type' => 'text',
					],
				],
			],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps);

		$account_step = null;
		foreach ($result as $step) {
			if ('account' === $step['id']) {
				$account_step = $step;
				break;
			}
		}

		$this->assertNotNull($account_step);
		$this->assertArrayNotHasKey('handler', $account_step);
		$this->assertArrayNotHasKey('view', $account_step);
		$this->assertArrayNotHasKey('hidden', $account_step);
	}

	/**
	 * Test convert_steps_to_v2 always appends a payment step.
	 */
	public function test_convert_steps_to_v2_appends_payment_step(): void {
		$old_steps = [
			'account' => [
				'name'   => 'Account',
				'fields' => [],
			],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps);

		// The last step should be the payment step
		$last_step = end($result);
		$this->assertEquals('payment', $last_step['id']);

		// Verify payment step has expected fields
		$field_types = array_column($last_step['fields'], 'type');
		$this->assertContains('order_summary', $field_types);
		$this->assertContains('billing_address', $field_types);
		$this->assertContains('discount_code', $field_types);
		$this->assertContains('payment', $field_types);
		$this->assertContains('submit_button', $field_types);
	}

	/**
	 * Test convert_steps_to_v2 with plan step.
	 */
	public function test_convert_steps_to_v2_with_plan_step(): void {
		$old_steps = [
			'plan' => [
				'name'   => 'Pick a Plan',
				'fields' => [
					'existing_field' => [
						'name' => 'Old Field',
						'type' => 'text',
					],
				],
			],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps);

		// Find the plan step
		$plan_step = null;
		foreach ($result as $step) {
			if ('plan' === $step['id']) {
				$plan_step = $step;
				break;
			}
		}

		$this->assertNotNull($plan_step);

		// Plan step should have a pricing_table field
		$field_types = array_column($plan_step['fields'], 'type');
		$this->assertContains('pricing_table', $field_types);
	}

	/**
	 * Test convert_steps_to_v2 excludes template step when no templates configured.
	 */
	public function test_convert_steps_to_v2_excludes_template_when_empty(): void {
		$old_steps = [
			'template' => [
				'name'   => 'Choose Template',
				'fields' => [],
			],
		];

		// No templates in old_settings
		$result = Checkout_Form::convert_steps_to_v2($old_steps, []);

		$step_ids = array_column($result, 'id');
		$this->assertNotContains('template', $step_ids);
	}

	/**
	 * Test convert_steps_to_v2 submit field becomes submit_button in account step.
	 */
	public function test_convert_steps_to_v2_submit_field_renamed_in_account(): void {
		$old_steps = [
			'account' => [
				'name'   => 'Account',
				'fields' => [
					'submit' => [
						'name' => 'Submit',
						'type' => 'submit',
					],
				],
			],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps);

		$account_step = null;
		foreach ($result as $step) {
			if ('account' === $step['id']) {
				$account_step = $step;
				break;
			}
		}

		$this->assertNotNull($account_step);

		$submit_field = null;
		foreach ($account_step['fields'] as $field) {
			if ('submit' === $field['id']) {
				$submit_field = $field;
				break;
			}
		}

		$this->assertNotNull($submit_field);
		$this->assertEquals('submit_button', $submit_field['type']);
		// In account step, name should be changed to 'Continue to the Next Step'
		$this->assertEquals('Continue to the Next Step', $submit_field['name']);
	}

	/**
	 * Test convert_steps_to_v2 with plan step period options.
	 */
	public function test_convert_steps_to_v2_plan_with_period_options(): void {
		$old_steps = [
			'plan' => [
				'name'   => 'Pick a Plan',
				'fields' => [],
			],
		];

		$old_settings = [
			'enable_price_1'  => true,
			'enable_price_3'  => true,
			'enable_price_12' => true,
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps, $old_settings);

		$plan_step = null;
		foreach ($result as $step) {
			if ('plan' === $step['id']) {
				$plan_step = $step;
				break;
			}
		}

		$this->assertNotNull($plan_step);

		// With multiple period options enabled, should have a period_selection field
		$field_types = array_column($plan_step['fields'], 'type');
		$this->assertContains('period_selection', $field_types);
		$this->assertContains('pricing_table', $field_types);
	}

	/**
	 * Test convert_steps_to_v2 plan with single period option does not add period selector.
	 */
	public function test_convert_steps_to_v2_plan_with_single_period(): void {
		$old_steps = [
			'plan' => [
				'name'   => 'Pick a Plan',
				'fields' => [],
			],
		];

		$old_settings = [
			'enable_price_1'  => true,
			'enable_price_3'  => false,
			'enable_price_12' => false,
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps, $old_settings);

		$plan_step = null;
		foreach ($result as $step) {
			if ('plan' === $step['id']) {
				$plan_step = $step;
				break;
			}
		}

		$this->assertNotNull($plan_step);

		// With only one period option, should NOT have period_selection field
		$field_types = array_column($plan_step['fields'], 'type');
		$this->assertNotContains('period_selection', $field_types);
		$this->assertContains('pricing_table', $field_types);
	}

	/**
	 * Test settings with JSON-encoded string (non-serialized).
	 */
	public function test_settings_with_non_serialized_string(): void {
		$checkout_form = new Checkout_Form();

		// An arbitrary string that is not a valid PHP serialized format
		$checkout_form->set_settings('not-serialized-string');

		// get_settings should handle this gracefully (the maybe_unserialize returns the string as-is)
		$settings = $checkout_form->get_settings();
		$this->assertEquals('not-serialized-string', $settings);
	}

	/**
	 * Test get_all_fields with single step single field.
	 */
	public function test_get_all_fields_single_step_single_field(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'step1',
				'fields' => [
					['id' => 'email', 'type' => 'email'],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$all_fields = $checkout_form->get_all_fields();
		$this->assertCount(1, $all_fields);
		$this->assertEquals('email', $all_fields[0]['id']);
	}

	/**
	 * Test get_all_fields_by_type with empty fields.
	 */
	public function test_get_all_fields_by_type_empty_fields(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'fields' => [],
			],
		];

		$checkout_form->set_settings($settings);

		$result = $checkout_form->get_all_fields_by_type('text');
		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * Helper to build valid checkout form settings with all required field types.
	 *
	 * @param array $extra_fields Additional fields to include in the checkout step.
	 * @return array
	 */
	private function get_valid_settings(array $extra_fields = []): array {
		$fields = [
			['id' => 'username', 'type' => 'username'],
			['id' => 'password', 'type' => 'password'],
			['id' => 'email_address', 'type' => 'email'],
			['id' => 'site_title', 'type' => 'site_title'],
			['id' => 'site_url', 'type' => 'site_url'],
			['id' => 'billing_address', 'type' => 'billing_address'],
			['id' => 'order_summary', 'type' => 'order_summary'],
			['id' => 'payment', 'type' => 'payment'],
			['id' => 'submit', 'type' => 'submit_button'],
		];

		$fields = array_merge($fields, $extra_fields);

		return [
			[
				'id'     => 'checkout',
				'name'   => 'Checkout',
				'fields' => $fields,
			],
		];
	}

	/**
	 * Test creating a checkout form via wu_create_checkout_form and persisting.
	 */
	public function test_create_checkout_form_via_helper_function(): void {
		$settings = $this->get_valid_settings([
			['id' => 'email', 'type' => 'email'],
		]);

		$checkout_form = wu_create_checkout_form([
			'name'     => 'Persisted Form',
			'slug'     => 'persisted-form',
			'settings' => $settings,
		]);

		$this->assertNotWPError($checkout_form);
		$this->assertInstanceOf(Checkout_Form::class, $checkout_form);
		$this->assertGreaterThan(0, $checkout_form->get_id());
		$this->assertEquals('Persisted Form', $checkout_form->get_name());
		$this->assertEquals('persisted-form', $checkout_form->get_slug());
	}

	/**
	 * Test fetching a persisted checkout form by ID.
	 */
	public function test_get_checkout_form_by_id(): void {
		$checkout_form = wu_create_checkout_form([
			'name' => 'Fetch Test',
			'slug' => 'fetch-test-form',
		]);

		$this->assertNotWPError($checkout_form);

		$fetched = wu_get_checkout_form($checkout_form->get_id());

		$this->assertInstanceOf(Checkout_Form::class, $fetched);
		$this->assertEquals('Fetch Test', $fetched->get_name());
		$this->assertEquals('fetch-test-form', $fetched->get_slug());
	}

	/**
	 * Test fetching a persisted checkout form by slug.
	 */
	public function test_get_checkout_form_by_slug(): void {
		$checkout_form = wu_create_checkout_form([
			'name' => 'Slug Test',
			'slug' => 'slug-test-form',
		]);

		$this->assertNotWPError($checkout_form);

		$fetched = wu_get_checkout_form_by_slug('slug-test-form');

		$this->assertInstanceOf(Checkout_Form::class, $fetched);
		$this->assertEquals('Slug Test', $fetched->get_name());
	}

	/**
	 * Test that settings are preserved after save and fetch.
	 */
	public function test_settings_persist_after_save(): void {
		$settings = $this->get_valid_settings([
			['id' => 'field1', 'type' => 'email'],
			['id' => 'field2', 'type' => 'text'],
		]);

		$checkout_form = wu_create_checkout_form([
			'name'     => 'Settings Persist Test',
			'slug'     => 'settings-persist-test',
			'settings' => $settings,
		]);

		$this->assertNotWPError($checkout_form);

		$fetched = wu_get_checkout_form($checkout_form->get_id());

		$fetched_settings = $fetched->get_settings();
		$this->assertCount(1, $fetched_settings);
		$this->assertEquals('checkout', $fetched_settings[0]['id']);

		// 9 required fields + 2 extra fields = 11
		$this->assertCount(11, $fetched_settings[0]['fields']);
	}

	/**
	 * Test that allowed countries persist after save.
	 */
	public function test_allowed_countries_persist_after_save(): void {
		$checkout_form = wu_create_checkout_form([
			'name'              => 'Country Persist Test',
			'slug'              => 'country-persist-test',
			'allowed_countries' => ['US', 'CA', 'GB'],
		]);

		$this->assertNotWPError($checkout_form);

		$fetched = wu_get_checkout_form($checkout_form->get_id());

		$this->assertTrue($fetched->has_country_lock());
		$countries = $fetched->get_allowed_countries();
		$this->assertContains('US', $countries);
		$this->assertContains('CA', $countries);
		$this->assertContains('GB', $countries);
	}

	/**
	 * Test meta constants are correct.
	 */
	public function test_meta_constants(): void {
		$this->assertEquals('wu_thank_you_page_id', Checkout_Form::META_THANK_YOU_PAGE_ID);
		$this->assertEquals('wu_conversion_snippets', Checkout_Form::META_CONVERSION_SNIPPETS);
	}

	/**
	 * Test template value is null by default.
	 */
	public function test_template_default_is_null(): void {
		$checkout_form = new Checkout_Form();

		$this->assertNull($checkout_form->get_template());
	}

	/**
	 * Test step count returns zero for non-array settings.
	 */
	public function test_step_count_with_string_settings(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_settings('invalid-string');

		// get_step_count calls get_settings which returns the string,
		// then checks is_array, so should return 0
		$this->assertEquals(0, $checkout_form->get_step_count());
	}

	/**
	 * Test get_all_fields returns empty array with non-array settings value.
	 */
	public function test_get_all_fields_with_non_array_settings(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_settings('string-value');

		$result = $checkout_form->get_all_fields();
		$this->assertEquals([], $result);
	}

	/**
	 * Test validation rules contain slug with unique constraint including form ID.
	 */
	public function test_validation_rules_slug_unique_includes_id(): void {
		$checkout_form = wu_create_checkout_form([
			'name' => 'Validation Test',
			'slug' => 'validation-test-form',
		]);

		$this->assertNotWPError($checkout_form);

		$rules = $checkout_form->validation_rules();

		// The slug rule should include the form's ID for unique check
		$this->assertStringContainsString((string) $checkout_form->get_id(), $rules['slug']);
	}

	/**
	 * Test get_steps_to_show with empty settings returns empty array.
	 */
	public function test_get_steps_to_show_with_empty_settings(): void {
		$checkout_form = new Checkout_Form();

		$steps = $checkout_form->get_steps_to_show();
		$this->assertIsArray($steps);
		$this->assertEmpty($steps);
	}

	/**
	 * Test get_steps_to_show with step missing logged key defaults to always.
	 */
	public function test_get_steps_to_show_defaults_logged_to_always(): void {
		wp_set_current_user(0);

		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'no_logged_key',
				'fields' => [
					['id' => 'email', 'type' => 'email'],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$steps = $checkout_form->get_steps_to_show();

		// Step without 'logged' key should default to 'always' and be shown
		$step_ids = array_column($steps, 'id');
		$this->assertContains('no_logged_key', $step_ids);
	}

	/**
	 * Test active status with string values.
	 */
	public function test_active_status_with_string_values(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->set_active('yes');
		$this->assertTrue($checkout_form->is_active());

		$checkout_form->set_active('');
		$this->assertFalse($checkout_form->is_active());
	}

	/**
	 * Test querying checkout forms.
	 */
	public function test_query_checkout_forms(): void {
		wu_create_checkout_form([
			'name' => 'Query Form 1',
			'slug' => 'query-form-1',
		]);

		wu_create_checkout_form([
			'name' => 'Query Form 2',
			'slug' => 'query-form-2',
		]);

		$forms = wu_get_checkout_forms([
			'search' => 'Query Form',
		]);

		$this->assertIsArray($forms);
		$this->assertGreaterThanOrEqual(2, count($forms));
	}

	/**
	 * Test wu_get_checkout_form returns false for non-existent ID.
	 */
	public function test_get_checkout_form_returns_false_for_invalid_id(): void {
		$result = wu_get_checkout_form(999999);
		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug returns false for empty slug.
	 */
	public function test_get_checkout_form_by_slug_returns_false_for_empty(): void {
		$result = wu_get_checkout_form_by_slug('');
		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug with special slug 'wu-checkout'.
	 */
	public function test_get_checkout_form_by_slug_wu_checkout(): void {
		$result = wu_get_checkout_form_by_slug('wu-checkout');

		// Without a current membership, returns an empty checkout form
		$this->assertInstanceOf(Checkout_Form::class, $result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug with special slug 'wu-add-new-site'.
	 */
	public function test_get_checkout_form_by_slug_wu_add_new_site(): void {
		$result = wu_get_checkout_form_by_slug('wu-add-new-site');

		// Without a current membership, returns an empty checkout form
		$this->assertInstanceOf(Checkout_Form::class, $result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug with special slug 'wu-finish-checkout'.
	 */
	public function test_get_checkout_form_by_slug_wu_finish_checkout(): void {
		$result = wu_get_checkout_form_by_slug('wu-finish-checkout');

		// Without a current payment, returns an empty checkout form
		$this->assertInstanceOf(Checkout_Form::class, $result);
	}

	/**
	 * Test multi-step template field count is greater than single step.
	 */
	public function test_multi_step_has_more_fields_than_single_step(): void {
		$single = new Checkout_Form();
		$single->use_template('single-step');

		$multi = new Checkout_Form();
		$multi->use_template('multi-step');

		$this->assertGreaterThan($single->get_step_count(), $multi->get_step_count());
	}

	/**
	 * Test get_all_fields_by_type with array input for types.
	 */
	public function test_get_all_fields_by_type_with_array_input(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'fields' => [
					['id' => 'email', 'type' => 'email'],
					['id' => 'pass', 'type' => 'password'],
					['id' => 'user', 'type' => 'username'],
					['id' => 'title', 'type' => 'site_title'],
					['id' => 'url', 'type' => 'site_url'],
				],
			],
		];

		$checkout_form->set_settings($settings);

		// Get site-related fields
		$site_fields = $checkout_form->get_all_fields_by_type(['site_title', 'site_url']);
		$this->assertCount(2, $site_fields);

		// Get all field types at once
		$all_types = $checkout_form->get_all_fields_by_type(['email', 'password', 'username', 'site_title', 'site_url']);
		$this->assertCount(5, $all_types);
	}

	/**
	 * Test that conversion snippets with multiline content are preserved.
	 */
	public function test_conversion_snippets_multiline(): void {
		$checkout_form = new Checkout_Form();

		$snippets = "<script>\n  fbq('track', 'Purchase');\n  ga('send', 'event', 'conversion');\n</script>";
		$checkout_form->set_conversion_snippets($snippets);
		$this->assertEquals($snippets, $checkout_form->get_conversion_snippets());
	}

	/**
	 * Test allowed countries with serialized string containing backslashes.
	 */
	public function test_allowed_countries_with_slashed_serialized_string(): void {
		$checkout_form = new Checkout_Form();

		$countries  = ['US', 'CA'];
		$serialized = addslashes(serialize($countries));

		$checkout_form->set_allowed_countries($serialized);

		$result = $checkout_form->get_allowed_countries();
		$this->assertEquals($countries, $result);
	}

	/**
	 * Test settings with slashed serialized string.
	 */
	public function test_settings_with_slashed_serialized_string(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'step1',
				'fields' => [],
			],
		];

		$slashed = addslashes(serialize($settings));
		$checkout_form->set_settings($slashed);

		$result = $checkout_form->get_settings();
		$this->assertEquals($settings, $result);
	}

	/**
	 * Test convert_steps_to_v2 with submit field in non-account step.
	 */
	public function test_convert_steps_to_v2_submit_in_non_account_step(): void {
		$old_steps = [
			'other' => [
				'name'   => 'Other Step',
				'fields' => [
					'submit' => [
						'name' => 'Submit Form',
						'type' => 'submit',
					],
				],
			],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps);

		$other_step = null;
		foreach ($result as $step) {
			if ('other' === $step['id']) {
				$other_step = $step;
				break;
			}
		}

		$this->assertNotNull($other_step);

		$submit_field = null;
		foreach ($other_step['fields'] as $field) {
			if ('submit' === $field['id']) {
				$submit_field = $field;
				break;
			}
		}

		$this->assertNotNull($submit_field);
		$this->assertEquals('submit_button', $submit_field['type']);
		// In non-account step, name should remain as is
		$this->assertEquals('Submit Form', $submit_field['name']);
	}

	/**
	 * Test checkout form with blank/empty settings returns zero counts.
	 */
	public function test_empty_form_returns_zero_counts(): void {
		$checkout_form = new Checkout_Form();

		$this->assertEquals(0, $checkout_form->get_step_count());
		$this->assertEquals(0, $checkout_form->get_field_count());
	}

	/**
	 * Test get_field returns full field data including extra attributes.
	 */
	public function test_get_field_returns_extra_attributes(): void {
		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'checkout',
				'fields' => [
					[
						'id'          => 'email',
						'type'        => 'email',
						'name'        => 'Email Address',
						'required'    => true,
						'placeholder' => 'you@example.com',
						'tooltip'     => 'Enter your email',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$field = $checkout_form->get_field('checkout', 'email');

		$this->assertEquals('email', $field['id']);
		$this->assertEquals('Email Address', $field['name']);
		$this->assertTrue($field['required']);
		$this->assertEquals('you@example.com', $field['placeholder']);
		$this->assertEquals('Enter your email', $field['tooltip']);
	}

	/**
	 * Test that to_array() populates lazy-loaded meta properties (issue #469).
	 *
	 * thank_you_page_id and conversion_snippets are only loaded from meta when
	 * their getter is first called. Without the to_array() override they remain null.
	 */
	public function test_to_array_includes_lazy_loaded_meta_properties(): void {
		$form = new \WP_Ultimo\Models\Checkout_Form();
		$form->set_name('Test Form');
		$form->set_slug('test-form');
		$form->set_thank_you_page_id(42);
		$form->set_conversion_snippets('<script>ga("send","event")</script>');

		$array = $form->to_array();

		$this->assertIsArray($array, 'to_array() should return an array.');
		$this->assertArrayHasKey('thank_you_page_id', $array, 'to_array() must include thank_you_page_id.');
		$this->assertNotNull($array['thank_you_page_id'], 'thank_you_page_id must not be null in to_array() output.');
		$this->assertEquals(42, $array['thank_you_page_id'], 'thank_you_page_id must match the set value.');
		$this->assertArrayHasKey('conversion_snippets', $array, 'to_array() must include conversion_snippets.');
		$this->assertNotNull($array['conversion_snippets'], 'conversion_snippets must not be null in to_array() output.');
	}

	/**
	 * Test to_array() on a persisted form (has ID) triggers lazy-load of meta.
	 */
	public function test_to_array_on_persisted_form_triggers_meta_lazy_load(): void {
		$form = wu_create_checkout_form([
			'name' => 'Persisted Array Test',
			'slug' => 'persisted-array-test-' . wp_generate_uuid4(),
		]);

		$this->assertNotWPError($form);

		// Set meta values and save them
		$form->set_thank_you_page_id(0);
		$form->set_conversion_snippets('');

		$array = $form->to_array();

		$this->assertIsArray($array);
		$this->assertArrayHasKey('thank_you_page_id', $array);
		$this->assertArrayHasKey('conversion_snippets', $array);
	}

	/**
	 * Test get_steps_to_show shows logged_only steps for logged-in users.
	 */
	public function test_get_steps_to_show_shows_logged_only_for_logged_in_users(): void {
		// Create and log in a user.
		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'always_step',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
			[
				'id'     => 'logged_only_step',
				'logged' => 'logged_only',
				'fields' => [
					[
						'id'   => 'profile_name',
						'type' => 'text',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$steps_to_show = $checkout_form->get_steps_to_show();

		$step_ids = array_column($steps_to_show, 'id');
		$this->assertContains('always_step', $step_ids);
		$this->assertContains('logged_only_step', $step_ids);

		// Reset user.
		wp_set_current_user(0);
	}

	/**
	 * Test convert_steps_to_v2 with template step when allow_template is true.
	 */
	public function test_convert_steps_to_v2_with_template_step_allow_template_true(): void {
		$old_steps = [
			'template' => [
				'name'   => 'Choose Template',
				'fields' => [],
			],
		];

		$old_settings = [
			'allow_template' => true,
			'templates'      => ['template-a', 'template-b'],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps, $old_settings);

		// Template step should be included when allow_template is true and templates exist.
		$step_ids = array_column($result, 'id');
		$this->assertContains('template', $step_ids);

		// Find the template step.
		$template_step = null;
		foreach ($result as $step) {
			if ('template' === $step['id']) {
				$template_step = $step;
				break;
			}
		}

		$this->assertNotNull($template_step);

		// Should have a template_selection field.
		$field_types = array_column($template_step['fields'], 'type');
		$this->assertContains('template_selection', $field_types);
	}

	/**
	 * Test convert_steps_to_v2 with template step using old_template_list.
	 */
	public function test_convert_steps_to_v2_template_step_uses_old_template_list(): void {
		$old_steps = [
			'template' => [
				'name'   => 'Choose Template',
				'fields' => [],
			],
		];

		// Provide a non-empty template list so it uses array_flip of old_template_list.
		$old_settings = [
			'allow_template' => true,
			'templates'      => [1, 2, 3],
		];

		$result = Checkout_Form::convert_steps_to_v2($old_steps, $old_settings);

		$step_ids = array_column($result, 'id');
		$this->assertContains('template', $step_ids);
	}

	/**
	 * Test save() with template set applies the template before saving.
	 */
	public function test_save_with_template_applies_template(): void {
		$form = wu_create_checkout_form([
			'name'     => 'Template Save Test',
			'slug'     => 'template-save-test-' . wp_generate_uuid4(),
			'template' => 'single-step',
		]);

		$this->assertNotWPError($form);
		$this->assertInstanceOf(Checkout_Form::class, $form);

		// After save with template='single-step', settings should be populated.
		$fetched = wu_get_checkout_form($form->get_id());
		$settings = $fetched->get_settings();

		$this->assertNotEmpty($settings);
		$this->assertIsArray($settings);
		$this->assertCount(1, $settings);
		$this->assertEquals('checkout', $settings[0]['id']);
	}

	/**
	 * Test save() with multi-step template applies the template before saving.
	 */
	public function test_save_with_multi_step_template_applies_template(): void {
		$form = wu_create_checkout_form([
			'name'     => 'Multi Template Save Test',
			'slug'     => 'multi-template-save-test-' . wp_generate_uuid4(),
			'template' => 'multi-step',
		]);

		$this->assertNotWPError($form);

		$fetched = wu_get_checkout_form($form->get_id());
		$settings = $fetched->get_settings();

		$this->assertNotEmpty($settings);
		$this->assertCount(4, $settings);
	}

	/**
	 * Test finish_checkout_form_fields returns fields when payment_id is in request.
	 */
	public function test_finish_checkout_form_fields_with_payment_id_in_request(): void {
		// Create a customer and payment.
		$customer = wu_create_customer([
			'username' => 'finish-checkout-test-' . wp_generate_uuid4(),
			'email'    => 'finish-checkout-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$product = wu_create_product([
			'name'         => 'Finish Checkout Plan',
			'slug'         => 'finish-checkout-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 50,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
		]);

		$this->assertNotWPError($membership);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 50.00,
			'total'         => 50.00,
			'gateway'       => 'manual',
		]);

		$this->assertNotWPError($payment);

		// Set payment_id in request.
		$_REQUEST['payment_id'] = $payment->get_id();

		$fields = Checkout_Form::finish_checkout_form_fields();

		unset($_REQUEST['payment_id']);

		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);

		// Should have one step with id 'checkout'.
		$this->assertCount(1, $fields);
		$this->assertEquals('checkout', $fields[0]['id']);

		// Verify expected field types.
		$field_types = array_column($fields[0]['fields'], 'type');
		$this->assertContains('order_summary', $field_types);
		$this->assertContains('payment', $field_types);
		$this->assertContains('submit_button', $field_types);
	}

	/**
	 * Test pay_invoice_form_fields returns empty array without payment.
	 */
	public function test_pay_invoice_form_fields_returns_empty_without_payment(): void {
		$fields = Checkout_Form::pay_invoice_form_fields();
		$this->assertEquals([], $fields);
	}

	/**
	 * Test pay_invoice_form_fields returns fields when payment_id is in request.
	 */
	public function test_pay_invoice_form_fields_with_payment_id_in_request(): void {
		// Create a customer and payment.
		$customer = wu_create_customer([
			'username' => 'pay-invoice-test-' . wp_generate_uuid4(),
			'email'    => 'pay-invoice-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$product = wu_create_product([
			'name'         => 'Invoice Plan',
			'slug'         => 'invoice-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 75,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
		]);

		$this->assertNotWPError($membership);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 75.00,
			'total'         => 75.00,
			'gateway'       => 'manual',
		]);

		$this->assertNotWPError($payment);

		// Set payment_id in request.
		$_REQUEST['payment_id'] = $payment->get_id();

		$fields = Checkout_Form::pay_invoice_form_fields();

		unset($_REQUEST['payment_id']);

		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);

		// Should have one step with id 'checkout'.
		$this->assertCount(1, $fields);
		$this->assertEquals('checkout', $fields[0]['id']);
		$this->assertEquals('Pay Invoice', $fields[0]['name']);

		// Verify expected field types.
		$field_types = array_column($fields[0]['fields'], 'type');
		$this->assertContains('order_summary', $field_types);
		$this->assertContains('payment', $field_types);
		$this->assertContains('submit_button', $field_types);
	}

	/**
	 * Test membership_change_form_fields returns fields when membership_id is in request.
	 */
	public function test_membership_change_form_fields_with_membership_id_in_request(): void {
		// Create a customer, product, and membership.
		$customer = wu_create_customer([
			'username' => 'membership-change-test-' . wp_generate_uuid4(),
			'email'    => 'membership-change-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$product = wu_create_product([
			'name'         => 'Change Plan',
			'slug'         => 'change-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 100,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
		]);

		$this->assertNotWPError($membership);

		// Set membership_id in request.
		$_REQUEST['membership_id'] = $membership->get_id();

		$fields = Checkout_Form::membership_change_form_fields();

		unset($_REQUEST['membership_id']);

		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);

		// Should have one step with id 'checkout'.
		$this->assertCount(1, $fields);
		$this->assertEquals('checkout', $fields[0]['id']);

		// Verify expected field types in the step.
		$field_types = array_column($fields[0]['fields'], 'type');
		$this->assertContains('order_summary', $field_types);
		$this->assertContains('payment', $field_types);
		$this->assertContains('submit_button', $field_types);
	}

	/**
	 * Test membership_change_form_fields with a plan that has a group.
	 */
	public function test_membership_change_form_fields_with_plan_group(): void {
		$customer = wu_create_customer([
			'username' => 'group-change-test-' . wp_generate_uuid4(),
			'email'    => 'group-change-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$product = wu_create_product([
			'name'          => 'Group Plan',
			'slug'          => 'group-plan-' . wp_generate_uuid4(),
			'pricing_type'  => 'paid',
			'amount'        => 100,
			'currency'      => 'USD',
			'recurring'     => false,
			'type'          => 'plan',
			'product_group' => 'test-group',
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
		]);

		$this->assertNotWPError($membership);

		$_REQUEST['membership_id'] = $membership->get_id();

		$fields = Checkout_Form::membership_change_form_fields();

		unset($_REQUEST['membership_id']);

		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);
	}

	/**
	 * Test add_new_site_form_fields returns fields when membership is set via currents.
	 */
	public function test_add_new_site_form_fields_with_membership_via_currents(): void {
		$customer = wu_create_customer([
			'username' => 'add-site-test-' . wp_generate_uuid4(),
			'email'    => 'add-site-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$product = wu_create_product([
			'name'         => 'Add Site Plan',
			'slug'         => 'add-site-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 100,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
		]);

		$this->assertNotWPError($membership);

		// Set membership via currents.
		WP_Ultimo()->currents->set_membership($membership);

		$fields = Checkout_Form::add_new_site_form_fields();

		// Reset currents.
		WP_Ultimo()->currents->set_membership(null);

		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);

		// Should have at least one step (the 'create' step).
		$step_ids = array_column($fields, 'id');
		$this->assertContains('create', $step_ids);

		// Find the create step.
		$create_step = null;
		foreach ($fields as $step) {
			if ('create' === $step['id']) {
				$create_step = $step;
				break;
			}
		}

		$this->assertNotNull($create_step);

		$field_types = array_column($create_step['fields'], 'type');
		$this->assertContains('site_title', $field_types);
		$this->assertContains('site_url', $field_types);
		$this->assertContains('submit_button', $field_types);
	}

	/**
	 * Test get_steps_to_show hides guests_only steps for logged-in users.
	 */
	public function test_get_steps_to_show_hides_guest_steps_for_logged_in_users(): void {
		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$checkout_form = new Checkout_Form();

		$settings = [
			[
				'id'     => 'always_step',
				'logged' => 'always',
				'fields' => [
					[
						'id'   => 'email',
						'type' => 'email',
					],
				],
			],
			[
				'id'     => 'guest_only_step',
				'logged' => 'guests_only',
				'fields' => [
					[
						'id'   => 'signup',
						'type' => 'text',
					],
				],
			],
		];

		$checkout_form->set_settings($settings);

		$steps_to_show = $checkout_form->get_steps_to_show();

		$step_ids = array_column($steps_to_show, 'id');
		$this->assertContains('always_step', $step_ids);
		$this->assertNotContains('guest_only_step', $step_ids);

		wp_set_current_user(0);
	}

	/**
	 * Test finish_checkout_form_fields returns fields when payment hash is in request.
	 */
	public function test_finish_checkout_form_fields_with_payment_hash_in_request(): void {
		$customer = wu_create_customer([
			'username' => 'hash-checkout-test-' . wp_generate_uuid4(),
			'email'    => 'hash-checkout-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$product = wu_create_product([
			'name'         => 'Hash Checkout Plan',
			'slug'         => 'hash-checkout-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 50,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
		]);

		$this->assertNotWPError($membership);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 50.00,
			'total'         => 50.00,
			'gateway'       => 'manual',
		]);

		$this->assertNotWPError($payment);

		// Use the payment hash in the request.
		$_REQUEST['payment'] = $payment->get_hash();

		$fields = Checkout_Form::finish_checkout_form_fields();

		unset($_REQUEST['payment']);

		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);
		$this->assertCount(1, $fields);
		$this->assertEquals('checkout', $fields[0]['id']);
	}

	/**
	 * Test pay_invoice_form_fields with payment hash in request.
	 */
	public function test_pay_invoice_form_fields_with_payment_hash_in_request(): void {
		$customer = wu_create_customer([
			'username' => 'hash-invoice-test-' . wp_generate_uuid4(),
			'email'    => 'hash-invoice-' . wp_generate_uuid4() . '@example.com',
			'password' => 'password123',
		]);

		$this->assertNotWPError($customer);

		$product = wu_create_product([
			'name'         => 'Hash Invoice Plan',
			'slug'         => 'hash-invoice-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 60,
			'currency'     => 'USD',
			'recurring'    => false,
			'type'         => 'plan',
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => $product->get_id(),
		]);

		$this->assertNotWPError($membership);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 60.00,
			'total'         => 60.00,
			'gateway'       => 'manual',
		]);

		$this->assertNotWPError($payment);

		$_REQUEST['payment'] = $payment->get_hash();

		$fields = Checkout_Form::pay_invoice_form_fields();

		unset($_REQUEST['payment']);

		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);
		$this->assertCount(1, $fields);
		$this->assertEquals('checkout', $fields[0]['id']);
		$this->assertEquals('Pay Invoice', $fields[0]['name']);
	}

	/**
	 * Test simple template is accepted by validation rules.
	 */
	public function test_validation_rules_accept_simple_template(): void {
		$checkout_form = new Checkout_Form();
		$rules         = $checkout_form->validation_rules();

		$this->assertStringContainsString('simple', $rules['template']);
	}

	/**
	 * Test use_template with simple template returns non-empty settings.
	 */
	public function test_use_template_simple_returns_settings(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('simple');
		$settings = $checkout_form->get_settings();

		$this->assertNotEmpty($settings);
		$this->assertIsArray($settings);
	}

	/**
	 * Test simple template has exactly one step.
	 */
	public function test_simple_template_has_one_step(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('simple');
		$settings = $checkout_form->get_settings();

		$this->assertCount(1, $settings);
		$this->assertEquals('checkout', $settings[0]['id']);
	}

	/**
	 * Test simple template contains email field.
	 */
	public function test_simple_template_contains_email_field(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('simple');
		$settings = $checkout_form->get_settings();

		$field_types = array_column($settings[0]['fields'], 'type');
		$this->assertContains('email', $field_types);
	}

	/**
	 * Test simple template has password field with auto_generate_password enabled.
	 */
	public function test_simple_template_password_field_has_auto_generate(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('simple');
		$settings = $checkout_form->get_settings();

		$password_field = null;
		foreach ($settings[0]['fields'] as $field) {
			if ('password' === $field['type']) {
				$password_field = $field;
				break;
			}
		}

		$this->assertNotNull($password_field, 'Password field must exist in simple template');
		$this->assertArrayHasKey('auto_generate_password', $password_field);
		$this->assertTrue((bool) $password_field['auto_generate_password']);
	}

	/**
	 * Test simple template has username field with auto_generate_username enabled.
	 */
	public function test_simple_template_username_field_has_auto_generate(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('simple');
		$settings = $checkout_form->get_settings();

		$username_field = null;
		foreach ($settings[0]['fields'] as $field) {
			if ('username' === $field['type']) {
				$username_field = $field;
				break;
			}
		}

		$this->assertNotNull($username_field, 'Username field must exist in simple template');
		$this->assertArrayHasKey('auto_generate_username', $username_field);
		$this->assertTrue((bool) $username_field['auto_generate_username']);
	}

	/**
	 * Test simple template has site_title field with auto_generate_site_title enabled.
	 */
	public function test_simple_template_site_title_has_auto_generate(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('simple');
		$settings = $checkout_form->get_settings();

		$site_title_field = null;
		foreach ($settings[0]['fields'] as $field) {
			if ('site_title' === $field['type']) {
				$site_title_field = $field;
				break;
			}
		}

		$this->assertNotNull($site_title_field, 'Site title field must exist in simple template');
		$this->assertArrayHasKey('auto_generate_site_title', $site_title_field);
		$this->assertTrue((bool) $site_title_field['auto_generate_site_title']);
	}

	/**
	 * Test simple template has site_url field with auto_generate_site_url enabled.
	 */
	public function test_simple_template_site_url_has_auto_generate(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('simple');
		$settings = $checkout_form->get_settings();

		$site_url_field = null;
		foreach ($settings[0]['fields'] as $field) {
			if ('site_url' === $field['type']) {
				$site_url_field = $field;
				break;
			}
		}

		$this->assertNotNull($site_url_field, 'Site URL field must exist in simple template');
		$this->assertArrayHasKey('auto_generate_site_url', $site_url_field);
		$this->assertTrue((bool) $site_url_field['auto_generate_site_url']);
	}

	/**
	 * Test simple template contains all required checkout fields.
	 */
	public function test_simple_template_contains_required_checkout_fields(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('simple');
		$settings = $checkout_form->get_settings();

		$field_types = array_column($settings[0]['fields'], 'type');

		$this->assertContains('email', $field_types);
		$this->assertContains('username', $field_types);
		$this->assertContains('password', $field_types);
		$this->assertContains('site_title', $field_types);
		$this->assertContains('site_url', $field_types);
		$this->assertContains('order_summary', $field_types);
		$this->assertContains('payment', $field_types);
		$this->assertContains('submit_button', $field_types);
	}

	/**
	 * Test simple template is filterable via wu_checkout_form_simple_template hook.
	 */
	public function test_simple_template_is_filterable(): void {
		$filter_called = false;

		add_filter('wu_checkout_form_simple_template', function ($steps) use (&$filter_called) {
			$filter_called = true;
			return $steps;
		});

		$checkout_form = new Checkout_Form();
		$checkout_form->use_template('simple');

		remove_all_filters('wu_checkout_form_simple_template');

		$this->assertTrue($filter_called, 'wu_checkout_form_simple_template filter must be applied');
	}

	/**
	 * Test simple template is applied on save when template is set to simple.
	 */
	public function test_simple_template_applied_on_save(): void {
		$checkout_form = wu_create_checkout_form([
			'name'     => 'Simple Template Save Test',
			'slug'     => 'simple-template-save-test',
			'template' => 'simple',
		]);

		$this->assertNotWPError($checkout_form);

		$fetched  = wu_get_checkout_form($checkout_form->get_id());
		$settings = $fetched->get_settings();

		$this->assertNotEmpty($settings);
		$field_types = array_column($settings[0]['fields'], 'type');
		$this->assertContains('email', $field_types);
		$this->assertContains('password', $field_types);
	}

	/**
	 * Test single-step template includes a template_selection field showing all templates.
	 */
	public function test_single_step_template_contains_template_selection_field(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('single-step');
		$settings = $checkout_form->get_settings();

		$field_types = array_column($settings[0]['fields'], 'type');
		$this->assertContains('template_selection', $field_types, 'Single-step template must include a template_selection field');

		// Locate the field and verify it is configured to show all templates.
		$template_field = null;
		foreach ($settings[0]['fields'] as $field) {
			if ('template_selection' === $field['type']) {
				$template_field = $field;
				break;
			}
		}

		$this->assertNotNull($template_field, 'template_selection field must be present');
		$this->assertEquals('all', $template_field['template_selection_type'], 'template_selection_type must be "all"');
	}

	/**
	 * Test multi-step template includes a template_selection field in the site step showing all templates.
	 */
	public function test_multi_step_template_site_step_contains_template_selection_field(): void {
		$checkout_form = new Checkout_Form();

		$checkout_form->use_template('multi-step');
		$settings = $checkout_form->get_settings();

		// Find the 'site' step.
		$site_step = null;
		foreach ($settings as $step) {
			if ('site' === $step['id']) {
				$site_step = $step;
				break;
			}
		}

		$this->assertNotNull($site_step, 'Multi-step template must have a "site" step');

		$field_types = array_column($site_step['fields'], 'type');
		$this->assertContains('template_selection', $field_types, 'Multi-step "site" step must include a template_selection field');

		// Locate the field and verify it is configured to show all templates.
		$template_field = null;
		foreach ($site_step['fields'] as $field) {
			if ('template_selection' === $field['type']) {
				$template_field = $field;
				break;
			}
		}

		$this->assertNotNull($template_field, 'template_selection field must be present in the site step');
		$this->assertEquals('all', $template_field['template_selection_type'], 'template_selection_type must be "all"');
	}
}

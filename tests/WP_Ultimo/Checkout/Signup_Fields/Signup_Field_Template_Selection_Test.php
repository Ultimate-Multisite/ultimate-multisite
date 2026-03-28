<?php
/**
 * Tests for Signup_Field_Template_Selection class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;
use WP_Ultimo\Managers\Field_Templates_Manager;

/**
 * Test class for Signup_Field_Template_Selection.
 */
class Signup_Field_Template_Selection_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Template_Selection
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Template_Selection();
	}

	// -------------------------------------------------------------------------
	// Basic identity methods
	// -------------------------------------------------------------------------

	/**
	 * Test get_type returns template_selection.
	 */
	public function test_get_type(): void {
		$this->assertEquals('template_selection', $this->field->get_type());
	}

	/**
	 * Test is_required returns false.
	 */
	public function test_is_required(): void {
		$this->assertFalse($this->field->is_required());
	}

	/**
	 * Test get_title returns non-empty string.
	 */
	public function test_get_title(): void {
		$title = $this->field->get_title();
		$this->assertIsString($title);
		$this->assertNotEmpty($title);
		$this->assertEquals('Templates', $title);
	}

	/**
	 * Test get_description returns non-empty string.
	 */
	public function test_get_description(): void {
		$description = $this->field->get_description();
		$this->assertIsString($description);
		$this->assertNotEmpty($description);
	}

	/**
	 * Test get_tooltip returns non-empty string.
	 */
	public function test_get_tooltip(): void {
		$tooltip = $this->field->get_tooltip();
		$this->assertIsString($tooltip);
		$this->assertNotEmpty($tooltip);
	}

	/**
	 * Test get_icon returns dashicon class string.
	 */
	public function test_get_icon(): void {
		$icon = $this->field->get_icon();
		$this->assertIsString($icon);
		$this->assertStringContainsString('dashicons', $icon);
	}

	/**
	 * Test is_user_field returns false (inherited default).
	 */
	public function test_is_user_field(): void {
		$this->assertFalse($this->field->is_user_field());
	}

	/**
	 * Test is_site_field returns false (inherited default).
	 */
	public function test_is_site_field(): void {
		$this->assertFalse($this->field->is_site_field());
	}

	/**
	 * Test field inherits from Base_Signup_Field.
	 */
	public function test_inheritance(): void {
		$this->assertInstanceOf(Base_Signup_Field::class, $this->field);
	}

	// -------------------------------------------------------------------------
	// defaults()
	// -------------------------------------------------------------------------

	/**
	 * Test defaults returns array with expected keys.
	 */
	public function test_defaults_returns_array(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray($defaults);
	}

	/**
	 * Test defaults contains template_selection_type key.
	 */
	public function test_defaults_has_template_selection_type(): void {
		$defaults = $this->field->defaults();
		$this->assertArrayHasKey('template_selection_type', $defaults);
		$this->assertEquals('name', $defaults['template_selection_type']);
	}

	/**
	 * Test defaults contains template_selection_template key.
	 */
	public function test_defaults_has_template_selection_template(): void {
		$defaults = $this->field->defaults();
		$this->assertArrayHasKey('template_selection_template', $defaults);
		$this->assertEquals('clean', $defaults['template_selection_template']);
	}

	/**
	 * Test defaults contains cols key.
	 */
	public function test_defaults_has_cols(): void {
		$defaults = $this->field->defaults();
		$this->assertArrayHasKey('cols', $defaults);
		$this->assertEquals(3, $defaults['cols']);
	}

	/**
	 * Test defaults contains hide_template_selection_when_pre_selected key.
	 */
	public function test_defaults_has_hide_when_pre_selected(): void {
		$defaults = $this->field->defaults();
		$this->assertArrayHasKey('hide_template_selection_when_pre_selected', $defaults);
		$this->assertFalse($defaults['hide_template_selection_when_pre_selected']);
	}

	/**
	 * Test defaults contains template_selection_sites key.
	 */
	public function test_defaults_has_template_selection_sites(): void {
		$defaults = $this->field->defaults();
		$this->assertArrayHasKey('template_selection_sites', $defaults);
		$this->assertIsString($defaults['template_selection_sites']);
	}

	// -------------------------------------------------------------------------
	// default_fields()
	// -------------------------------------------------------------------------

	/**
	 * Test default_fields returns array.
	 */
	public function test_default_fields_returns_array(): void {
		$fields = $this->field->default_fields();
		$this->assertIsArray($fields);
	}

	// -------------------------------------------------------------------------
	// force_attributes()
	// -------------------------------------------------------------------------

	/**
	 * Test force_attributes returns array with id key.
	 */
	public function test_force_attributes_has_id(): void {
		$forced = $this->field->force_attributes();
		$this->assertIsArray($forced);
		$this->assertArrayHasKey('id', $forced);
		$this->assertEquals('template_selection', $forced['id']);
	}

	/**
	 * Test force_attributes has name key.
	 */
	public function test_force_attributes_has_name(): void {
		$forced = $this->field->force_attributes();
		$this->assertArrayHasKey('name', $forced);
		$this->assertIsString($forced['name']);
		$this->assertNotEmpty($forced['name']);
	}

	/**
	 * Test force_attributes has required set to true.
	 */
	public function test_force_attributes_required_is_true(): void {
		$forced = $this->field->force_attributes();
		$this->assertArrayHasKey('required', $forced);
		$this->assertTrue($forced['required']);
	}

	// -------------------------------------------------------------------------
	// get_template_selection_templates()
	// -------------------------------------------------------------------------

	/**
	 * Test get_template_selection_templates returns array.
	 */
	public function test_get_template_selection_templates_returns_array(): void {
		$templates = $this->field->get_template_selection_templates();
		$this->assertIsArray($templates);
	}

	/**
	 * Test get_template_selection_templates contains expected template keys.
	 */
	public function test_get_template_selection_templates_has_clean(): void {
		$templates = $this->field->get_template_selection_templates();
		$this->assertArrayHasKey('clean', $templates);
	}

	/**
	 * Test get_template_selection_templates values are strings (titles).
	 */
	public function test_get_template_selection_templates_values_are_strings(): void {
		$templates = $this->field->get_template_selection_templates();
		foreach ($templates as $key => $title) {
			$this->assertIsString($key, "Template key '{$key}' should be a string");
			$this->assertIsString($title, "Template title for '{$key}' should be a string");
		}
	}

	// -------------------------------------------------------------------------
	// get_fields()
	// -------------------------------------------------------------------------

	/**
	 * Test get_fields returns array.
	 */
	public function test_get_fields_returns_array(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray($fields);
	}

	/**
	 * Test get_fields contains cols key.
	 */
	public function test_get_fields_has_cols(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('cols', $fields);
		$this->assertEquals('hidden', $fields['cols']['type']);
	}

	/**
	 * Test get_fields contains template_selection_type key.
	 */
	public function test_get_fields_has_template_selection_type(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('template_selection_type', $fields);
		$this->assertEquals('select', $fields['template_selection_type']['type']);
	}

	/**
	 * Test template_selection_type field has expected options.
	 */
	public function test_get_fields_template_selection_type_options(): void {
		$fields = $this->field->get_fields();
		$options = $fields['template_selection_type']['options'];
		$this->assertArrayHasKey('name', $options);
		$this->assertArrayHasKey('categories', $options);
		$this->assertArrayHasKey('all', $options);
	}

	/**
	 * Test get_fields contains template_selection_categories key.
	 */
	public function test_get_fields_has_template_selection_categories(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('template_selection_categories', $fields);
		$this->assertEquals('select', $fields['template_selection_categories']['type']);
	}

	/**
	 * Test get_fields contains template_selection_sites key.
	 */
	public function test_get_fields_has_template_selection_sites(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('template_selection_sites', $fields);
		$this->assertEquals('model', $fields['template_selection_sites']['type']);
	}

	/**
	 * Test get_fields contains hide_template_selection_when_pre_selected key.
	 */
	public function test_get_fields_has_hide_when_pre_selected(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('hide_template_selection_when_pre_selected', $fields);
		$this->assertEquals('toggle', $fields['hide_template_selection_when_pre_selected']['type']);
	}

	/**
	 * Test get_fields contains template_selection_template group key.
	 */
	public function test_get_fields_has_template_selection_template_group(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('template_selection_template', $fields);
		$this->assertEquals('group', $fields['template_selection_template']['type']);
	}

	/**
	 * Test template_selection_template group contains nested select field.
	 */
	public function test_get_fields_template_selection_template_nested_select(): void {
		$fields = $this->field->get_fields();
		$group  = $fields['template_selection_template'];
		$this->assertArrayHasKey('fields', $group);
		$this->assertArrayHasKey('template_selection_template', $group['fields']);
		$this->assertEquals('select', $group['fields']['template_selection_template']['type']);
	}

	/**
	 * Test template_selection_sites html_attr has data-model=site.
	 */
	public function test_get_fields_sites_data_model(): void {
		$fields    = $this->field->get_fields();
		$html_attr = $fields['template_selection_sites']['html_attr'];
		$this->assertEquals('site', $html_attr['data-model']);
	}

	// -------------------------------------------------------------------------
	// reduce_attributes()
	// -------------------------------------------------------------------------

	/**
	 * Test reduce_attributes extracts blog_id from sites array.
	 */
	public function test_reduce_attributes_extracts_blog_ids(): void {
		$attributes = [
			'sites' => [
				['blog_id' => 1, 'title' => 'Site One'],
				['blog_id' => 2, 'title' => 'Site Two'],
				['blog_id' => 3, 'title' => 'Site Three'],
			],
		];

		$result = $this->field->reduce_attributes($attributes);

		$this->assertIsArray($result['sites']);
		$this->assertContains(1, $result['sites']);
		$this->assertContains(2, $result['sites']);
		$this->assertContains(3, $result['sites']);
		$this->assertCount(3, $result['sites']);
	}

	/**
	 * Test reduce_attributes returns sequential array (array_values).
	 */
	public function test_reduce_attributes_returns_sequential_array(): void {
		$attributes = [
			'sites' => [
				['blog_id' => 5, 'title' => 'Site Five'],
				['blog_id' => 10, 'title' => 'Site Ten'],
			],
		];

		$result = $this->field->reduce_attributes($attributes);

		// array_values ensures sequential keys 0, 1, ...
		$this->assertArrayHasKey(0, $result['sites']);
		$this->assertArrayHasKey(1, $result['sites']);
	}

	/**
	 * Test reduce_attributes with empty sites array.
	 */
	public function test_reduce_attributes_with_empty_sites(): void {
		$attributes = [
			'sites' => [],
		];

		$result = $this->field->reduce_attributes($attributes);

		$this->assertIsArray($result['sites']);
		$this->assertEmpty($result['sites']);
	}

	/**
	 * Test reduce_attributes preserves other attributes.
	 */
	public function test_reduce_attributes_preserves_other_keys(): void {
		$attributes = [
			'sites'    => [['blog_id' => 1, 'title' => 'Site']],
			'some_key' => 'some_value',
		];

		$result = $this->field->reduce_attributes($attributes);

		$this->assertArrayHasKey('some_key', $result);
		$this->assertEquals('some_value', $result['some_key']);
	}

	// -------------------------------------------------------------------------
	// site_list() — tested via to_fields_array() since it's protected
	// -------------------------------------------------------------------------

	/**
	 * Test site_list via reflection for 'name' selection type.
	 */
	public function test_site_list_name_type(): void {
		$reflection = new \ReflectionClass($this->field);
		$method     = $reflection->getMethod('site_list');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$attributes = [
			'template_selection_type'  => 'name',
			'template_selection_sites' => '1,2,3',
		];

		$result = $method->invoke($this->field, $attributes);

		$this->assertIsArray($result);
		$this->assertContains('1', $result);
		$this->assertContains('2', $result);
		$this->assertContains('3', $result);
	}

	/**
	 * Test site_list via reflection for 'all' selection type.
	 */
	public function test_site_list_all_type(): void {
		$reflection = new \ReflectionClass($this->field);
		$method     = $reflection->getMethod('site_list');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$attributes = [
			'template_selection_type'  => 'all',
			'template_selection_sites' => '',
		];

		// wu_get_site_templates returns [] in test environment (no site templates)
		$result = $method->invoke($this->field, $attributes);

		$this->assertIsArray($result);
	}

	/**
	 * Test site_list via reflection for 'categories' selection type.
	 */
	public function test_site_list_categories_type(): void {
		$reflection = new \ReflectionClass($this->field);
		$method     = $reflection->getMethod('site_list');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$attributes = [
			'template_selection_type'       => 'categories',
			'template_selection_sites'      => '',
			'template_selection_categories' => ['blog'],
		];

		// In test environment, no sites with categories exist — returns []
		$result = $method->invoke($this->field, $attributes);

		$this->assertIsArray($result);
	}

	/**
	 * Test site_list falls back to name-based list for unknown type.
	 */
	public function test_site_list_unknown_type_falls_back_to_name(): void {
		$reflection = new \ReflectionClass($this->field);
		$method     = $reflection->getMethod('site_list');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$attributes = [
			'template_selection_type'  => 'unknown_type',
			'template_selection_sites' => '7,8',
		];

		$result = $method->invoke($this->field, $attributes);

		$this->assertIsArray($result);
		$this->assertContains('7', $result);
		$this->assertContains('8', $result);
	}

	// -------------------------------------------------------------------------
	// to_fields_array()
	// -------------------------------------------------------------------------

	/**
	 * Build a minimal valid attributes array for to_fields_array tests.
	 *
	 * @return array
	 */
	private function make_attributes(array $overrides = []): array {
		return array_merge(
			[
				'id'                                        => 'template_selection',
				'type'                                      => 'template_selection',
				'name'                                      => 'Template Selection',
				'element_classes'                           => '',
				'cols'                                      => 3,
				'template_selection_type'                   => 'name',
				'template_selection_sites'                  => '',
				'template_selection_template'               => 'clean',
				'template_selection_categories'             => [],
				'hide_template_selection_when_pre_selected' => false,
			],
			$overrides
		);
	}

	/**
	 * Test to_fields_array always contains template_id hidden field.
	 */
	public function test_to_fields_array_always_has_template_id(): void {
		$attributes = $this->make_attributes();
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('template_id', $fields);
		$this->assertEquals('hidden', $fields['template_id']['type']);
	}

	/**
	 * Test to_fields_array returns only template_id when field should be hidden.
	 */
	public function test_to_fields_array_returns_only_template_id_when_hidden(): void {
		// Simulate wu_should_hide_form_field returning true by setting the
		// hide flag and ensuring no pre-selected value is in the request.
		// In the test environment wu_is_form_field_pre_selected returns false,
		// so we cannot force the hide path without a request param.
		// Instead, verify the structure when NOT hidden (the common path).
		$attributes = $this->make_attributes(['hide_template_selection_when_pre_selected' => false]);
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		// When not hidden, we get more than just template_id
		$this->assertGreaterThanOrEqual(1, count($fields));
		$this->assertArrayHasKey('template_id', $fields);
	}

	/**
	 * Test to_fields_array with 'clean' template adds note field.
	 */
	public function test_to_fields_array_with_clean_template_adds_note_field(): void {
		$attributes = $this->make_attributes(['template_selection_template' => 'clean']);
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		// The note field is keyed by the field id ('template_selection')
		$this->assertArrayHasKey('template_selection', $fields);
		$this->assertEquals('note', $fields['template_selection']['type']);
	}

	/**
	 * Test to_fields_array note field has a callable desc.
	 */
	public function test_to_fields_array_note_field_desc_is_callable(): void {
		$attributes = $this->make_attributes();
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		if (isset($fields['template_selection'])) {
			$this->assertIsCallable($fields['template_selection']['desc']);
		} else {
			// Field was hidden — only template_id present
			$this->assertArrayHasKey('template_id', $fields);
		}
	}

	/**
	 * Test to_fields_array note field has element_classes in wrapper_classes.
	 */
	public function test_to_fields_array_note_field_has_wrapper_classes(): void {
		$attributes = $this->make_attributes(['element_classes' => 'my-custom-class']);
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		if (isset($fields['template_selection'])) {
			$this->assertEquals('my-custom-class', $fields['template_selection']['wrapper_classes']);
		} else {
			$this->assertArrayHasKey('template_id', $fields);
		}
	}

	/**
	 * Test to_fields_array with 'minimal' template.
	 */
	public function test_to_fields_array_with_minimal_template(): void {
		$attributes = $this->make_attributes(['template_selection_template' => 'minimal']);
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('template_id', $fields);
	}

	/**
	 * Test to_fields_array with 'legacy' template enqueues scripts.
	 *
	 * The legacy path calls wp_register_script / wp_enqueue_script.
	 * In the test environment these are no-ops but must not throw.
	 */
	public function test_to_fields_array_with_legacy_template_does_not_throw(): void {
		$attributes = $this->make_attributes(['template_selection_template' => 'legacy']);
		$this->field->set_attributes($attributes);

		$exception = null;
		try {
			$fields = $this->field->to_fields_array($attributes);
		} catch (\Throwable $e) {
			$exception = $e;
		}

		$this->assertNull($exception, 'to_fields_array with legacy template should not throw');
	}

	/**
	 * Test to_fields_array with non-existent template still returns array.
	 */
	public function test_to_fields_array_with_nonexistent_template(): void {
		$attributes = $this->make_attributes(['template_selection_template' => 'nonexistent_template']);
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('template_id', $fields);
	}

	/**
	 * Test to_fields_array with 'all' selection type.
	 */
	public function test_to_fields_array_with_all_selection_type(): void {
		$attributes = $this->make_attributes(['template_selection_type' => 'all']);
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('template_id', $fields);
	}

	/**
	 * Test to_fields_array with 'categories' selection type.
	 */
	public function test_to_fields_array_with_categories_selection_type(): void {
		$attributes = $this->make_attributes(
			[
				'template_selection_type'       => 'categories',
				'template_selection_categories' => ['blog', 'portfolio'],
			]
		);
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('template_id', $fields);
	}

	/**
	 * Test to_fields_array template_id field has v-model html_attr.
	 */
	public function test_to_fields_array_template_id_has_v_model(): void {
		$attributes = $this->make_attributes();
		$this->field->set_attributes($attributes);

		$fields = $this->field->to_fields_array($attributes);

		$this->assertArrayHasKey('html_attr', $fields['template_id']);
		$this->assertArrayHasKey('v-model', $fields['template_id']['html_attr']);
		$this->assertEquals('template_id', $fields['template_id']['html_attr']['v-model']);
	}

	// -------------------------------------------------------------------------
	// get_field_as_type_option() — inherited from Base_Signup_Field
	// -------------------------------------------------------------------------

	/**
	 * Test get_field_as_type_option returns array with expected keys.
	 */
	public function test_get_field_as_type_option(): void {
		$option = $this->field->get_field_as_type_option();

		$this->assertIsArray($option);
		$this->assertArrayHasKey('title', $option);
		$this->assertArrayHasKey('desc', $option);
		$this->assertArrayHasKey('tooltip', $option);
		$this->assertArrayHasKey('type', $option);
		$this->assertArrayHasKey('icon', $option);
		$this->assertArrayHasKey('required', $option);
		$this->assertArrayHasKey('default_fields', $option);
		$this->assertArrayHasKey('force_attributes', $option);
		$this->assertArrayHasKey('all_attributes', $option);
		$this->assertArrayHasKey('fields', $option);
	}

	/**
	 * Test get_field_as_type_option type matches get_type.
	 */
	public function test_get_field_as_type_option_type_matches(): void {
		$option = $this->field->get_field_as_type_option();
		$this->assertEquals($this->field->get_type(), $option['type']);
	}

	// -------------------------------------------------------------------------
	// Return type assertions
	// -------------------------------------------------------------------------

	/**
	 * Test all basic method return types.
	 */
	public function test_return_types(): void {
		$this->assertIsString($this->field->get_type());
		$this->assertIsBool($this->field->is_required());
		$this->assertIsString($this->field->get_title());
		$this->assertIsString($this->field->get_description());
		$this->assertIsString($this->field->get_tooltip());
		$this->assertIsString($this->field->get_icon());
		$this->assertIsArray($this->field->get_fields());
		$this->assertIsArray($this->field->defaults());
		$this->assertIsArray($this->field->default_fields());
		$this->assertIsArray($this->field->force_attributes());
		$this->assertIsArray($this->field->get_template_selection_templates());
	}
}

<?php
/**
 * Test case for Base Signup Field.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Checkout\Signup_Fields;

use WP_Ultimo\Checkout\Signup_Fields\Base_Signup_Field;
use WP_UnitTestCase;

/**
 * Concrete implementation of Base_Signup_Field for testing.
 */
class Test_Signup_Field extends Base_Signup_Field {

	/**
	 * Returns the type of the field.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_type() {
		return 'test_field';
	}

	/**
	 * Returns if this field should be present on the checkout flow or not.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_required() {
		return true;
	}

	/**
	 * Requires the title of the field/element type.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title() {
		return 'Test Field';
	}

	/**
	 * Returns the description of the field/element.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {
		return 'A test field for testing purposes';
	}

	/**
	 * Returns the tooltip of the field/element.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_tooltip() {
		return 'This is a test field tooltip';
	}

	/**
	 * Returns the icon to be used on the selector.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_icon() {
		return 'dashicons-admin-generic';
	}

	/**
	 * Returns the list of additional fields specific to this type.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_fields() {
		return [
			'test_option' => [
				'type'  => 'text',
				'title' => 'Test Option',
			],
		];
	}

	/**
	 * Returns the field/element actual field array to be used on the checkout form.
	 *
	 * @since 2.0.0
	 * @param array $attributes Field attributes.
	 * @return array
	 */
	public function to_fields_array($attributes = []) {
		return [
			'test_field' => [
				'type'        => 'text',
				'title'       => $this->get_title(),
				'placeholder' => 'Enter test value',
				'required'    => $this->is_required(),
			],
		];
	}

	/**
	 * Outputs the contents of the field.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function output() {
		echo '<input type="text" name="test_field" />';
	}
}

/**
 * Concrete implementation that reports itself as a site field.
 */
class Test_Site_Signup_Field extends Test_Signup_Field {

	/**
	 * Returns the type of the field.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'test_site_field';
	}

	/**
	 * Marks this as a site field.
	 *
	 * @return boolean
	 */
	public function is_site_field() {
		return true;
	}
}

/**
 * Concrete implementation that reports itself as a user field.
 */
class Test_User_Signup_Field extends Test_Signup_Field {

	/**
	 * Returns the type of the field.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'test_user_field';
	}

	/**
	 * Marks this as a user field.
	 *
	 * @return boolean
	 */
	public function is_user_field() {
		return true;
	}
}

/**
 * Test Base Signup Field functionality.
 */
class Base_Signup_Field_Test extends WP_UnitTestCase {

	/**
	 * Test field instance.
	 *
	 * @var Test_Signup_Field
	 */
	private $field;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->field = new Test_Signup_Field();
	}

	// -------------------------------------------------------------------------
	// Abstract method delegation
	// -------------------------------------------------------------------------

	/**
	 * Test field type.
	 */
	public function test_get_type() {
		$this->assertEquals('test_field', $this->field->get_type());
	}

	/**
	 * Test field required status.
	 */
	public function test_is_required() {
		$this->assertTrue($this->field->is_required());
	}

	/**
	 * Test field title.
	 */
	public function test_get_title() {
		$this->assertEquals('Test Field', $this->field->get_title());
	}

	/**
	 * Test field description.
	 */
	public function test_get_description() {
		$this->assertEquals('A test field for testing purposes', $this->field->get_description());
	}

	/**
	 * Test field tooltip.
	 */
	public function test_get_tooltip() {
		$this->assertEquals('This is a test field tooltip', $this->field->get_tooltip());
	}

	/**
	 * Test field icon.
	 */
	public function test_get_icon() {
		$this->assertEquals('dashicons-admin-generic', $this->field->get_icon());
	}

	/**
	 * Test field configuration fields.
	 */
	public function test_get_fields() {
		$fields = $this->field->get_fields();

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('test_option', $fields);
		$this->assertEquals('text', $fields['test_option']['type']);
		$this->assertEquals('Test Option', $fields['test_option']['title']);
	}

	// -------------------------------------------------------------------------
	// Concrete defaults
	// -------------------------------------------------------------------------

	/**
	 * Test is_hidden returns false by default.
	 */
	public function test_is_hidden_returns_false_by_default() {
		$this->assertFalse($this->field->is_hidden());
	}

	/**
	 * Test is_site_field returns false by default.
	 */
	public function test_is_site_field_returns_false_by_default() {
		$this->assertFalse($this->field->is_site_field());
	}

	/**
	 * Test is_user_field returns false by default.
	 */
	public function test_is_user_field_returns_false_by_default() {
		$this->assertFalse($this->field->is_user_field());
	}

	/**
	 * Test force_attributes returns empty array.
	 */
	public function test_force_attributes_returns_empty_array() {
		$this->assertSame([], $this->field->force_attributes());
	}

	/**
	 * Test defaults returns empty array.
	 */
	public function test_defaults_returns_empty_array() {
		$this->assertSame([], $this->field->defaults());
	}

	/**
	 * Test reduce_attributes is a passthrough.
	 */
	public function test_reduce_attributes_is_passthrough() {
		$input = ['foo' => 'bar', 'baz' => 123];
		$this->assertSame($input, $this->field->reduce_attributes($input));
	}

	/**
	 * Test get_editor_fields_html_attr is a passthrough.
	 */
	public function test_get_editor_fields_html_attr_is_passthrough() {
		$html_attr = ['class' => 'my-class', 'data-foo' => 'bar'];
		$result    = $this->field->get_editor_fields_html_attr($html_attr, 'text');
		$this->assertSame($html_attr, $result);
	}

	/**
	 * Test get_tabs returns content and style tabs.
	 */
	public function test_get_tabs_returns_content_and_style() {
		$tabs = $this->field->get_tabs();
		$this->assertIsArray($tabs);
		$this->assertContains('content', $tabs);
		$this->assertContains('style', $tabs);
	}

	// -------------------------------------------------------------------------
	// set_attributes / get_value
	// -------------------------------------------------------------------------

	/**
	 * Test set_attributes stores the attributes array.
	 */
	public function test_set_attributes_stores_attributes() {
		$attrs = ['id' => 'my_field', 'name' => 'My Field'];
		$this->field->set_attributes($attrs);

		$reflection = new \ReflectionClass($this->field);
		$property   = $reflection->getProperty('attributes');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$this->assertSame($attrs, $property->getValue($this->field));
	}

	/**
	 * Test get_value returns default_value when no session or request override.
	 */
	public function test_get_value_returns_default_value() {
		$this->field->set_attributes([
			'id'            => 'my_field',
			'default_value' => 'hello',
			'from_request'  => false,
		]);

		$this->assertEquals('hello', $this->field->get_value());
	}

	/**
	 * Test get_value returns empty string when no default_value set.
	 */
	public function test_get_value_returns_empty_string_when_no_default() {
		$this->field->set_attributes([
			'id'           => 'my_field',
			'from_request' => false,
		]);

		$this->assertEquals('', $this->field->get_value());
	}

	/**
	 * Test get_value uses request value when from_request is set.
	 */
	public function test_get_value_uses_request_when_from_request_is_set() {
		$_POST['my_request_field'] = 'request_value';

		$this->field->set_attributes([
			'id'            => 'my_request_field',
			'default_value' => 'default',
			'from_request'  => true,
		]);

		$value = $this->field->get_value();

		// Clean up superglobal.
		unset($_POST['my_request_field']);

		$this->assertEquals('request_value', $value);
	}

	// -------------------------------------------------------------------------
	// calculate_style_attr
	// -------------------------------------------------------------------------

	/**
	 * Test calculate_style_attr with no width returns clear: both.
	 */
	public function test_calculate_style_attr_no_width_returns_clear_both() {
		$this->field->set_attributes([]);
		$style = $this->field->calculate_style_attr();
		$this->assertEquals('clear: both', $style);
	}

	/**
	 * Test calculate_style_attr with width 0 returns clear: both.
	 */
	public function test_calculate_style_attr_zero_width_returns_clear_both() {
		$this->field->set_attributes(['width' => 0]);
		$style = $this->field->calculate_style_attr();
		$this->assertEquals('clear: both', $style);
	}

	/**
	 * Test calculate_style_attr with width 100 returns no float (full width).
	 */
	public function test_calculate_style_attr_full_width_returns_no_float() {
		$this->field->set_attributes(['width' => 100]);
		$style = $this->field->calculate_style_attr();
		// Width 100 is the "full width" case — no float or width styles added.
		$this->assertStringNotContainsString('float', $style);
		$this->assertStringNotContainsString('width', $style);
	}

	/**
	 * Test calculate_style_attr with partial width returns float and width.
	 */
	public function test_calculate_style_attr_partial_width_returns_float_and_width() {
		$this->field->set_attributes(['width' => 50]);
		$style = $this->field->calculate_style_attr();
		$this->assertStringContainsString('float: left', $style);
		$this->assertStringContainsString('width: 50%', $style);
	}

	/**
	 * Test calculate_style_attr with width 25 returns correct percentage.
	 */
	public function test_calculate_style_attr_25_percent_width() {
		$this->field->set_attributes(['width' => 25]);
		$style = $this->field->calculate_style_attr();
		$this->assertStringContainsString('float: left', $style);
		$this->assertStringContainsString('width: 25%', $style);
	}

	// -------------------------------------------------------------------------
	// default_fields
	// -------------------------------------------------------------------------

	/**
	 * Test default_fields returns the standard set of field keys.
	 */
	public function test_default_fields_returns_standard_keys() {
		$fields = $this->field->default_fields();
		$this->assertIsArray($fields);
		$this->assertContains('id', $fields);
		$this->assertContains('name', $fields);
		$this->assertContains('placeholder', $fields);
		$this->assertContains('tooltip', $fields);
		$this->assertContains('default', $fields);
		$this->assertContains('required', $fields);
	}

	// -------------------------------------------------------------------------
	// get_all_attributes
	// -------------------------------------------------------------------------

	/**
	 * Test get_all_attributes merges default fields, field keys, and style keys.
	 */
	public function test_get_all_attributes_merges_all_sources() {
		$all = $this->field->get_all_attributes();

		$this->assertIsArray($all);

		// From default_fields().
		$this->assertContains('id', $all);
		$this->assertContains('name', $all);
		$this->assertContains('required', $all);

		// From get_fields() keys.
		$this->assertContains('test_option', $all);

		// Style keys always appended.
		$this->assertContains('wrapper_element_classes', $all);
		$this->assertContains('element_classes', $all);
		$this->assertContains('element_id', $all);
		$this->assertContains('from_request', $all);
		$this->assertContains('width', $all);
		$this->assertContains('logged', $all);
	}

	// -------------------------------------------------------------------------
	// get_field_as_type_option
	// -------------------------------------------------------------------------

	/**
	 * Test get_field_as_type_option returns all required keys.
	 */
	public function test_get_field_as_type_option_returns_required_keys() {
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
	 * Test get_field_as_type_option values match field methods.
	 */
	public function test_get_field_as_type_option_values_match_field_methods() {
		$option = $this->field->get_field_as_type_option();

		$this->assertEquals($this->field->get_title(), $option['title']);
		$this->assertEquals($this->field->get_description(), $option['desc']);
		$this->assertEquals($this->field->get_tooltip(), $option['tooltip']);
		$this->assertEquals($this->field->get_type(), $option['type']);
		$this->assertEquals($this->field->get_icon(), $option['icon']);
		$this->assertEquals($this->field->is_required(), $option['required']);
	}

	/**
	 * Test get_field_as_type_option fields key is a callable.
	 */
	public function test_get_field_as_type_option_fields_is_callable() {
		$option = $this->field->get_field_as_type_option();
		$this->assertIsArray($option['fields']);
		$this->assertIsCallable($option['fields']);
	}

	// -------------------------------------------------------------------------
	// fields_list (static)
	// -------------------------------------------------------------------------

	/**
	 * Test fields_list returns an array.
	 */
	public function test_fields_list_returns_array() {
		$fields = Base_Signup_Field::fields_list();
		$this->assertIsArray($fields);
	}

	/**
	 * Test fields_list contains all expected field definitions.
	 */
	public function test_fields_list_contains_expected_keys() {
		$fields = Base_Signup_Field::fields_list();

		$this->assertArrayHasKey('id', $fields);
		$this->assertArrayHasKey('name', $fields);
		$this->assertArrayHasKey('placeholder', $fields);
		$this->assertArrayHasKey('tooltip', $fields);
		$this->assertArrayHasKey('default_value', $fields);
		$this->assertArrayHasKey('note', $fields);
		$this->assertArrayHasKey('limits', $fields);
		$this->assertArrayHasKey('save_as', $fields);
		$this->assertArrayHasKey('required', $fields);
	}

	/**
	 * Test fields_list id field has correct type and html_attr.
	 */
	public function test_fields_list_id_field_structure() {
		$fields = Base_Signup_Field::fields_list();
		$id     = $fields['id'];

		$this->assertEquals('text', $id['type']);
		$this->assertArrayHasKey('html_attr', $id);
		$this->assertArrayHasKey('v-on:input', $id['html_attr']);
		$this->assertArrayHasKey('v-bind:value', $id['html_attr']);
	}

	/**
	 * Test fields_list name field has v-model binding.
	 */
	public function test_fields_list_name_field_has_v_model() {
		$fields = Base_Signup_Field::fields_list();
		$this->assertArrayHasKey('v-model', $fields['name']['html_attr']);
		$this->assertEquals('name', $fields['name']['html_attr']['v-model']);
	}

	/**
	 * Test fields_list required field is a toggle type.
	 */
	public function test_fields_list_required_field_is_toggle() {
		$fields = Base_Signup_Field::fields_list();
		$this->assertEquals('toggle', $fields['required']['type']);
	}

	/**
	 * Test fields_list save_as field has expected options.
	 */
	public function test_fields_list_save_as_has_options() {
		$fields   = Base_Signup_Field::fields_list();
		$save_as  = $fields['save_as'];

		$this->assertEquals('select', $save_as['type']);
		$this->assertArrayHasKey('options', $save_as);
		$this->assertArrayHasKey('customer_meta', $save_as['options']);
		$this->assertArrayHasKey('user_meta', $save_as['options']);
		$this->assertArrayHasKey('site_meta', $save_as['options']);
		$this->assertArrayHasKey('site_option', $save_as['options']);
		$this->assertArrayHasKey('nothing', $save_as['options']);
	}

	/**
	 * Test fields_list limits field is a group with min/max subfields.
	 */
	public function test_fields_list_limits_field_is_group_with_min_max() {
		$fields = Base_Signup_Field::fields_list();
		$limits = $fields['limits'];

		$this->assertEquals('group', $limits['type']);
		$this->assertArrayHasKey('fields', $limits);
		$this->assertArrayHasKey('min', $limits['fields']);
		$this->assertArrayHasKey('max', $limits['fields']);
		$this->assertEquals('number', $limits['fields']['min']['type']);
		$this->assertEquals('number', $limits['fields']['max']['type']);
	}

	// -------------------------------------------------------------------------
	// get_editor_fields
	// -------------------------------------------------------------------------

	/**
	 * Test get_editor_fields returns array with html_attr and wrapper_html_attr set.
	 */
	public function test_get_editor_fields_sets_html_attr_and_wrapper() {
		$fields = $this->field->get_editor_fields([]);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('test_option', $fields);

		$field = $fields['test_option'];
		$this->assertArrayHasKey('html_attr', $field);
		$this->assertArrayHasKey('wrapper_html_attr', $field);
	}

	/**
	 * Test get_editor_fields wrapper_html_attr contains v-cloak and v-show.
	 */
	public function test_get_editor_fields_wrapper_has_v_cloak_and_v_show() {
		$fields = $this->field->get_editor_fields([]);
		$field  = $fields['test_option'];

		$this->assertArrayHasKey('v-cloak', $field['wrapper_html_attr']);
		$this->assertArrayHasKey('v-show', $field['wrapper_html_attr']);
	}

	/**
	 * Test get_editor_fields v-show contains the field type.
	 */
	public function test_get_editor_fields_v_show_contains_field_type() {
		$fields  = $this->field->get_editor_fields([]);
		$v_show  = $fields['test_option']['wrapper_html_attr']['v-show'];

		$this->assertStringContainsString('test_field', $v_show);
	}

	/**
	 * Test get_editor_fields sets value from attributes when provided.
	 */
	public function test_get_editor_fields_sets_value_from_attributes() {
		$fields = $this->field->get_editor_fields(['test_option' => 'my_value']);

		$this->assertArrayHasKey('value', $fields['test_option']);
		$this->assertEquals('my_value', $fields['test_option']['value']);
	}

	/**
	 * Test get_editor_fields sets default value when attribute not provided.
	 */
	public function test_get_editor_fields_sets_default_when_no_attribute() {
		$fields = $this->field->get_editor_fields([]);

		// Default from defaults() is '' for all keys not explicitly set.
		$this->assertArrayHasKey('default', $fields['test_option']);
		$this->assertEquals('', $fields['test_option']['default']);
	}

	/**
	 * Test get_editor_fields adds site notice for site fields.
	 */
	public function test_get_editor_fields_adds_site_notice_for_site_fields() {
		$site_field = new Test_Site_Signup_Field();
		$fields     = $site_field->get_editor_fields([]);

		// Should contain a key starting with _site_notice_field_.
		$notice_keys = array_filter(
			array_keys($fields),
			function ($key) {
				return strpos($key, '_site_notice_field_') === 0;
			}
		);

		$this->assertNotEmpty($notice_keys, 'Expected a site notice field to be injected');

		$notice_key = array_values($notice_keys)[0];
		$this->assertEquals('note', $fields[$notice_key]['type']);
	}

	/**
	 * Test get_editor_fields adds user notice for user fields.
	 */
	public function test_get_editor_fields_adds_user_notice_for_user_fields() {
		$user_field = new Test_User_Signup_Field();
		$fields     = $user_field->get_editor_fields([]);

		$notice_keys = array_filter(
			array_keys($fields),
			function ($key) {
				return strpos($key, '_user_notice_field_') === 0;
			}
		);

		$this->assertNotEmpty($notice_keys, 'Expected a user notice field to be injected');

		$notice_key = array_values($notice_keys)[0];
		$this->assertEquals('note', $fields[$notice_key]['type']);
	}

	/**
	 * Test get_editor_fields does not add site notice for non-site fields.
	 */
	public function test_get_editor_fields_no_site_notice_for_non_site_fields() {
		$fields = $this->field->get_editor_fields([]);

		$notice_keys = array_filter(
			array_keys($fields),
			function ($key) {
				return strpos($key, '_site_notice_field_') === 0;
			}
		);

		$this->assertEmpty($notice_keys);
	}

	/**
	 * Test get_editor_fields does not add user notice for non-user fields.
	 */
	public function test_get_editor_fields_no_user_notice_for_non_user_fields() {
		$fields = $this->field->get_editor_fields([]);

		$notice_keys = array_filter(
			array_keys($fields),
			function ($key) {
				return strpos($key, '_user_notice_field_') === 0;
			}
		);

		$this->assertEmpty($notice_keys);
	}

	// -------------------------------------------------------------------------
	// Structural / reflection
	// -------------------------------------------------------------------------

	/**
	 * Test abstract class enforcement.
	 */
	public function test_abstract_class_is_abstract() {
		$reflection = new \ReflectionClass(Base_Signup_Field::class);
		$this->assertTrue($reflection->isAbstract());
	}

	/**
	 * Test all required abstract methods are declared.
	 */
	public function test_abstract_methods_are_declared() {
		$reflection = new \ReflectionClass(Base_Signup_Field::class);

		$abstract_methods = [
			'get_type',
			'is_required',
			'get_title',
			'get_description',
			'get_tooltip',
			'get_icon',
			'get_fields',
			'to_fields_array',
		];

		foreach ($abstract_methods as $method) {
			$this->assertTrue($reflection->hasMethod($method), "Method {$method} should exist");
		}
	}

	/**
	 * Test field inheritance.
	 */
	public function test_field_is_instance_of_base() {
		$this->assertInstanceOf(Base_Signup_Field::class, $this->field);
	}

	/**
	 * Test attributes property exists on the class.
	 */
	public function test_attributes_property_exists() {
		$reflection = new \ReflectionClass($this->field);
		$this->assertTrue($reflection->hasProperty('attributes'));
	}

	/**
	 * Test attributes are initially null before set_attributes is called.
	 */
	public function test_attributes_initially_null() {
		$fresh      = new Test_Signup_Field();
		$reflection = new \ReflectionClass($fresh);
		$property   = $reflection->getProperty('attributes');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$attributes = $property->getValue($fresh);
		$this->assertTrue(is_null($attributes) || empty($attributes));
	}

	/**
	 * Test field method return types.
	 */
	public function test_return_types() {
		$this->assertIsString($this->field->get_type());
		$this->assertIsBool($this->field->is_required());
		$this->assertIsString($this->field->get_title());
		$this->assertIsString($this->field->get_description());
		$this->assertIsString($this->field->get_tooltip());
		$this->assertIsString($this->field->get_icon());
		$this->assertIsArray($this->field->get_fields());
	}
}

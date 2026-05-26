<?php
/**
 * Tests for Field class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Test class for Field.
 */
class Field_Test extends WP_UnitTestCase {

	/**
	 * Test class keeps the expected name for autoloaded consumers.
	 */
	public function test_class_uses_expected_name(): void {
		$field = new Field('test_field', []);

		$this->assertInstanceOf(Field::class, $field);
		$this->assertSame(Field::class, get_class($field));
	}

	/**
	 * Test constructor creates field with attributes.
	 */
	public function test_constructor(): void {
		$field = new Field('test_field', [
			'type'  => 'text',
			'title' => 'Test Field',
		]);

		$atts = $field->get_attributes();
		$this->assertEquals('test_field', $atts['id']);
		$this->assertEquals('text', $atts['type']);
		$this->assertEquals('Test Field', $atts['title']);
	}

	/**
	 * Test default values are set.
	 */
	public function test_default_values(): void {
		$field = new Field('test_field', []);

		$atts = $field->get_attributes();
		$this->assertEquals('text', $atts['type']);
		$this->assertEquals('dashicons-wu-cog', $atts['icon']);
		$this->assertFalse($atts['sortable']);
		$this->assertEquals(1, $atts['columns']);
	}

	/**
	 * Test set_attribute method.
	 */
	public function test_set_attribute(): void {
		$field = new Field('test_field', ['type' => 'text']);

		$field->set_attribute('title', 'New Title');

		$atts = $field->get_attributes();
		$this->assertEquals('New Title', $atts['title']);
	}

	/**
	 * Test get_attributes returns array.
	 */
	public function test_get_attributes(): void {
		$field = new Field('test_field', [
			'type'        => 'text',
			'placeholder' => 'Enter text',
		]);

		$atts = $field->get_attributes();

		$this->assertIsArray($atts);
		$this->assertArrayHasKey('id', $atts);
		$this->assertArrayHasKey('type', $atts);
		$this->assertArrayHasKey('placeholder', $atts);
	}

	/**
	 * Test magic getter returns attribute values.
	 */
	public function test_magic_getter(): void {
		$field = new Field('test_field', [
			'type'  => 'text',
			'title' => 'Test Title',
		]);

		$this->assertEquals('text', $field->type);
		$this->assertEquals('Test Title', $field->title);
		$this->assertEquals('test_field', $field->id);
	}

	/**
	 * Test get_template_name returns string.
	 */
	public function test_get_template_name(): void {
		$field = new Field('test_field', ['type' => 'text']);

		$template = $field->get_template_name();

		$this->assertIsString($template);
		$this->assertEquals('text', $template);
	}

	/**
	 * Test get_template_name with underscores.
	 */
	public function test_get_template_name_with_underscores(): void {
		$field = new Field('test_field', ['type' => 'multi_checkbox']);

		$template = $field->get_template_name();

		$this->assertEquals('multi-checkbox', $template);
	}

	/**
	 * Test get_compat_template_name with heading alias.
	 */
	public function test_get_compat_template_name_heading(): void {
		$field = new Field('test_field', ['type' => 'heading']);

		$compat = $field->get_compat_template_name();

		$this->assertEquals('header', $compat);
	}

	/**
	 * Test get_compat_template_name with select2 alias.
	 */
	public function test_get_compat_template_name_select2(): void {
		$field = new Field('test_field', ['type' => 'select2']);

		$compat = $field->get_compat_template_name();

		$this->assertEquals('select', $compat);
	}

	/**
	 * Test get_compat_template_name returns false for non-aliased types.
	 */
	public function test_get_compat_template_name_no_alias(): void {
		$field = new Field('test_field', ['type' => 'text']);

		$compat = $field->get_compat_template_name();

		$this->assertFalse($compat);
	}

	/**
	 * Test field with number type.
	 */
	public function test_number_field(): void {
		$field = new Field('test_field', [
			'type' => 'number',
			'min'  => 0,
			'max'  => 100,
		]);

		$this->assertEquals('number', $field->type);
		$this->assertEquals(0, $field->min);
		$this->assertEquals(100, $field->max);
	}

	/**
	 * Test field with select type and options.
	 */
	public function test_select_field_with_options(): void {
		$options = [
			'option1' => 'Option 1',
			'option2' => 'Option 2',
		];

		$field = new Field('test_field', [
			'type'    => 'select',
			'options' => $options,
		]);

		$this->assertEquals('select', $field->type);
		$this->assertEquals($options, $field->options);
	}

	/**
	 * Test field with html attributes.
	 */
	public function test_field_with_html_attr(): void {
		$field = new Field('test_field', [
			'type'      => 'text',
			'html_attr' => [
				'data-custom' => 'value',
				'readonly'    => true,
			],
		]);

		$this->assertEquals('value', $field->html_attr['data-custom']);
		$this->assertTrue($field->html_attr['readonly']);
	}

	/**
	 * Test field with wrapper classes.
	 */
	public function test_field_with_wrapper_classes(): void {
		$field = new Field('test_field', [
			'type'            => 'text',
			'wrapper_classes' => 'custom-wrapper',
		]);

		$atts = $field->get_attributes();
		$this->assertStringContainsString('custom-wrapper', $atts['wrapper_classes']);
	}

	/**
	 * Test field jsonSerialize.
	 */
	public function test_json_serialize(): void {
		$field = new Field('test_field', [
			'type'  => 'text',
			'title' => 'Test',
		]);

		$json = json_encode($field);

		$this->assertIsString($json);
		$this->assertJson($json);
	}

	/**
	 * Test field with required attribute.
	 */
	public function test_field_with_required(): void {
		$field = new Field('test_field', [
			'type'    => 'text',
			'require' => true,
		]);

		$this->assertTrue($field->require);
	}

	/**
	 * Test field with tooltip.
	 */
	public function test_field_with_tooltip(): void {
		$field = new Field('test_field', [
			'type'    => 'text',
			'tooltip' => 'This is a helpful tip',
		]);

		$this->assertEquals('This is a helpful tip', $field->tooltip);
	}

	/**
	 * Test title falls back to name.
	 */
	public function test_title_fallback_to_name(): void {
		$field = new Field('test_field', [
			'type'  => 'text',
			'name'  => 'Field Name',
			'title' => false,
		]);

		// Accessing title should fallback to name
		$this->assertEquals('Field Name', $field->title);
	}

	/**
	 * Textarea sanitization must accept a plain string and round-trip safely.
	 */
	public function test_textarea_sanitization_string_value(): void {
		$field = new Field('test_field', [
			'type'    => 'textarea',
			'default' => '',
		]);

		$field->set_value("Line one\nLine two");

		$this->assertSame("Line one\nLine two", $field->get_value());
	}

	/**
	 * Regression: textarea sanitization must NOT fatal when given an array value.
	 *
	 * This reproduces the historical TypeError raised by addslashes() / stripslashes()
	 * on PHP 8+ when a stored DB value (or a malformed POST payload) reaches the
	 * textarea validator as an array. Expected behaviour is to coerce to an empty
	 * string rather than fatal the Settings save / Setup Wizard step.
	 */
	public function test_textarea_sanitization_coerces_array_to_empty_string(): void {
		$field = new Field('test_field', [
			'type'       => 'textarea',
			'allow_html' => false,
			'default'    => '',
		]);

		$field->set_value(['unexpected', 'array', 'value']);

		$this->assertSame('', $field->get_value());
	}

	/**
	 * Regression: wp_editor (allow_html=true) must also tolerate array input.
	 */
	public function test_wp_editor_sanitization_coerces_array_to_empty_string(): void {
		$field = new Field('test_field', [
			'type'       => 'wp_editor',
			'allow_html' => true,
			'default'    => '',
		]);

		$field->set_value(['<p>not</p>', '<p>a</p>', '<p>string</p>']);

		$this->assertSame('', $field->get_value());
	}

	/**
	 * Object values (Closures, stdClass) must coerce to empty string, not fatal.
	 */
	public function test_textarea_sanitization_coerces_object_to_empty_string(): void {
		$field = new Field('test_field', [
			'type'    => 'textarea',
			'default' => '',
		]);

		$field->set_value((object) ['foo' => 'bar']);

		$this->assertSame('', $field->get_value());
	}

	/**
	 * Null values must coerce to empty string.
	 */
	public function test_textarea_sanitization_coerces_null_to_empty_string(): void {
		$field = new Field('test_field', [
			'type'    => 'textarea',
			'default' => '',
		]);

		$field->set_value(null);

		$this->assertSame('', $field->get_value());
	}

	/**
	 * Scalar non-strings (int, float, bool) must cast to their string form.
	 */
	public function test_textarea_sanitization_casts_scalar_to_string(): void {
		$field = new Field('test_field', [
			'type'    => 'textarea',
			'default' => '',
		]);

		$field->set_value(42);
		$this->assertSame('42', $field->get_value());

		$field->set_value(3.14);
		$this->assertSame('3.14', $field->get_value());
	}
}

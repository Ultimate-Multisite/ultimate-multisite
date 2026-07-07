<?php
/**
 * Tests for Signup_Field_Select class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Select.
 */
class Signup_Field_Select_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Select
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Select();
	}

	/**
	 * Test get_type returns select.
	 */
	public function test_get_type(): void {
		$this->assertEquals('select', $this->field->get_type());
	}

	/**
	 * Test is_required returns false.
	 */
	public function test_is_required(): void {
		$this->assertFalse($this->field->is_required());
	}

	/**
	 * Test get_title returns string.
	 */
	public function test_get_title(): void {
		$title = $this->field->get_title();
		$this->assertIsString($title);
		$this->assertEquals('Select', $title);
	}

	/**
	 * Test get_description returns string.
	 */
	public function test_get_description(): void {
		$description = $this->field->get_description();
		$this->assertIsString($description);
		$this->assertNotEmpty($description);
	}

	/**
	 * Test get_tooltip returns string.
	 */
	public function test_get_tooltip(): void {
		$tooltip = $this->field->get_tooltip();
		$this->assertIsString($tooltip);
		$this->assertNotEmpty($tooltip);
	}

	/**
	 * Test get_icon returns dashicon class.
	 */
	public function test_get_icon(): void {
		$icon = $this->field->get_icon();
		$this->assertIsString($icon);
		$this->assertStringContainsString('dashicons', $icon);
	}

	/**
	 * Test is_user_field returns false.
	 */
	public function test_is_user_field(): void {
		$this->assertFalse($this->field->is_user_field());
	}

	/**
	 * Test is_site_field returns false.
	 */
	public function test_is_site_field(): void {
		$this->assertFalse($this->field->is_site_field());
	}

	/**
	 * Test defaults returns array.
	 */
	public function test_defaults(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray($defaults);
	}

	/**
	 * Test default_fields contains expected fields.
	 */
	public function test_default_fields(): void {
		$fields = $this->field->default_fields();
		$this->assertIsArray($fields);
		$this->assertContains('id', $fields);
		$this->assertContains('name', $fields);
		$this->assertContains('placeholder', $fields);
		$this->assertContains('required', $fields);
	}

	/**
	 * Test get_fields returns array with expected keys.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray($fields);
		$this->assertArrayHasKey('options_header', $fields);
		$this->assertArrayHasKey('options', $fields);
	}

	/**
	 * Test to_fields_array returns proper structure.
	 */
	public function test_to_fields_array(): void {
		$attributes = [
			'id'              => 'country_select',
			'name'            => 'Select Country',
			'placeholder'     => 'Choose a country',
			'tooltip'         => 'Select your country',
			'default_value'   => 'us',
			'required'        => true,
			'element_classes' => 'select-class',
			'options'         => [
				[
					'key'   => 'us',
					'label' => 'United States',
				],
				[
					'key'   => 'uk',
					'label' => 'United Kingdom',
				],
				[
					'key'   => 'ca',
					'label' => 'Canada',
				],
			],
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('country_select', $fields);
		$this->assertEquals('select', $fields['country_select']['type']);
		$this->assertEquals('Select Country', $fields['country_select']['name']);
		$this->assertTrue($fields['country_select']['required']);

		// Check options are properly formatted
		$this->assertArrayHasKey('options', $fields['country_select']);
		$this->assertEquals('United States', $fields['country_select']['options']['us']);
		$this->assertEquals('United Kingdom', $fields['country_select']['options']['uk']);
	}

	/**
	 * Test to_fields_array with empty options.
	 */
	public function test_to_fields_array_empty_options(): void {
		$attributes = [
			'id'              => 'empty_select',
			'name'            => 'Empty Select',
			'placeholder'     => '',
			'tooltip'         => '',
			'default_value'   => '',
			'required'        => false,
			'element_classes' => '',
			'options'         => [],
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('empty_select', $fields);
		$this->assertEmpty($fields['empty_select']['options']);
	}
}

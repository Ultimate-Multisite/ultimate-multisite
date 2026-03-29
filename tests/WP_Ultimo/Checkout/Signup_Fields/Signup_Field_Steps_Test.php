<?php
/**
 * Tests for Signup_Field_Steps class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Steps.
 */
class Signup_Field_Steps_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Steps
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Steps();
	}

	/**
	 * Test get_type returns steps.
	 */
	public function test_get_type(): void {
		$this->assertEquals( 'steps', $this->field->get_type() );
	}

	/**
	 * Test is_required returns false.
	 */
	public function test_is_required(): void {
		$this->assertFalse( $this->field->is_required() );
	}

	/**
	 * Test get_title returns non-empty string.
	 */
	public function test_get_title(): void {
		$title = $this->field->get_title();
		$this->assertIsString( $title );
		$this->assertNotEmpty( $title );
	}

	/**
	 * Test get_description returns non-empty string.
	 */
	public function test_get_description(): void {
		$description = $this->field->get_description();
		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
	}

	/**
	 * Test get_tooltip returns non-empty string.
	 */
	public function test_get_tooltip(): void {
		$tooltip = $this->field->get_tooltip();
		$this->assertIsString( $tooltip );
		$this->assertNotEmpty( $tooltip );
	}

	/**
	 * Test get_icon returns dashicon class.
	 */
	public function test_get_icon(): void {
		$icon = $this->field->get_icon();
		$this->assertIsString( $icon );
		$this->assertStringContainsString( 'dashicons', $icon );
	}

	/**
	 * Test is_user_field returns false by default.
	 */
	public function test_is_user_field(): void {
		$this->assertFalse( $this->field->is_user_field() );
	}

	/**
	 * Test is_site_field returns false by default.
	 */
	public function test_is_site_field(): void {
		$this->assertFalse( $this->field->is_site_field() );
	}

	/**
	 * Test defaults returns expected keys.
	 */
	public function test_defaults(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'steps_template', $defaults );
		$this->assertEquals( 'clean', $defaults['steps_template'] );
	}

	/**
	 * Test default_fields returns empty array.
	 */
	public function test_default_fields(): void {
		$fields = $this->field->default_fields();
		$this->assertIsArray( $fields );
		$this->assertEmpty( $fields );
	}

	/**
	 * Test force_attributes returns id = steps.
	 */
	public function test_force_attributes(): void {
		$attrs = $this->field->force_attributes();
		$this->assertIsArray( $attrs );
		$this->assertArrayHasKey( 'id', $attrs );
		$this->assertEquals( 'steps', $attrs['id'] );
	}

	/**
	 * Test get_fields returns array with steps_template key.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'steps_template', $fields );
	}

	/**
	 * Test get_fields steps_template is group type.
	 */
	public function test_get_fields_steps_template_is_group(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'group', $fields['steps_template']['type'] );
		$this->assertArrayHasKey( 'fields', $fields['steps_template'] );
	}

	/**
	 * Test get_fields steps_template group contains steps_template select.
	 */
	public function test_get_fields_steps_template_group_has_select(): void {
		$fields       = $this->field->get_fields();
		$group_fields = $fields['steps_template']['fields'];

		$this->assertArrayHasKey( 'steps_template', $group_fields );
		$this->assertEquals( 'select', $group_fields['steps_template']['type'] );
	}

	/**
	 * Test get_templates returns array.
	 */
	public function test_get_templates(): void {
		$templates = $this->field->get_templates();
		$this->assertIsArray( $templates );
	}

	/**
	 * Test field is instance of Base_Signup_Field.
	 */
	public function test_field_is_instance_of_base(): void {
		$this->assertInstanceOf( Base_Signup_Field::class, $this->field );
	}
}

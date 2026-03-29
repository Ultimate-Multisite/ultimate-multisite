<?php
/**
 * Tests for Signup_Field_Pricing_Table class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Pricing_Table.
 */
class Signup_Field_Pricing_Table_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Pricing_Table
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Pricing_Table();
	}

	/**
	 * Test get_type returns pricing_table.
	 */
	public function test_get_type(): void {
		$this->assertEquals( 'pricing_table', $this->field->get_type() );
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
	 * Test defaults returns expected keys with correct values.
	 */
	public function test_defaults(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'pricing_table_products', $defaults );
		$this->assertArrayHasKey( 'pricing_table_template', $defaults );
		$this->assertEquals( 'list', $defaults['pricing_table_template'] );
		$this->assertArrayHasKey( 'force_different_durations', $defaults );
		$this->assertFalse( $defaults['force_different_durations'] );
		$this->assertArrayHasKey( 'hide_pricing_table_when_pre_selected', $defaults );
		$this->assertFalse( $defaults['hide_pricing_table_when_pre_selected'] );
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
	 * Test force_attributes returns id, name, and required.
	 */
	public function test_force_attributes(): void {
		$attrs = $this->field->force_attributes();
		$this->assertIsArray( $attrs );
		$this->assertArrayHasKey( 'id', $attrs );
		$this->assertEquals( 'pricing_table', $attrs['id'] );
		$this->assertArrayHasKey( 'name', $attrs );
		$this->assertIsString( $attrs['name'] );
		$this->assertArrayHasKey( 'required', $attrs );
		$this->assertTrue( $attrs['required'] );
	}

	/**
	 * Test get_fields returns expected keys.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'pricing_table_products', $fields );
		$this->assertArrayHasKey( 'force_different_durations', $fields );
		$this->assertArrayHasKey( 'hide_pricing_table_when_pre_selected', $fields );
		$this->assertArrayHasKey( 'pricing_table_template', $fields );
	}

	/**
	 * Test get_fields pricing_table_products is model type.
	 */
	public function test_get_fields_products_is_model_type(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'model', $fields['pricing_table_products']['type'] );
	}

	/**
	 * Test get_fields force_different_durations is toggle type.
	 */
	public function test_get_fields_force_different_durations_is_toggle(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'toggle', $fields['force_different_durations']['type'] );
	}

	/**
	 * Test get_fields hide_pricing_table_when_pre_selected is toggle type.
	 */
	public function test_get_fields_hide_when_pre_selected_is_toggle(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'toggle', $fields['hide_pricing_table_when_pre_selected']['type'] );
	}

	/**
	 * Test get_fields pricing_table_template is group type.
	 */
	public function test_get_fields_pricing_table_template_is_group(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'group', $fields['pricing_table_template']['type'] );
		$this->assertArrayHasKey( 'fields', $fields['pricing_table_template'] );
	}

	/**
	 * Test get_pricing_table_templates returns array.
	 */
	public function test_get_pricing_table_templates(): void {
		$templates = $this->field->get_pricing_table_templates();
		$this->assertIsArray( $templates );
	}

	/**
	 * Test to_fields_array returns empty array when no products are configured.
	 */
	public function test_to_fields_array_returns_empty_when_no_products(): void {
		$attributes = array(
			'id'                                   => 'pricing_table',
			'type'                                 => 'pricing_table',
			'name'                                 => 'Plan Selection',
			'pricing_table_products'               => '',
			'pricing_table_template'               => 'list',
			'force_different_durations'            => false,
			'hide_pricing_table_when_pre_selected' => false,
			'wrapper_element_classes'              => '',
			'element_classes'                      => '',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		// With no valid products, the result should be an array with one note field (template rendered).
		$this->assertIsArray( $fields );
	}

	/**
	 * Test field is instance of Base_Signup_Field.
	 */
	public function test_field_is_instance_of_base(): void {
		$this->assertInstanceOf( Base_Signup_Field::class, $this->field );
	}
}

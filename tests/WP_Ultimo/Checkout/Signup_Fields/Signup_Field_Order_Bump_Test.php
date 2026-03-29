<?php
/**
 * Tests for Signup_Field_Order_Bump class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Order_Bump.
 */
class Signup_Field_Order_Bump_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Order_Bump
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Order_Bump();
	}

	/**
	 * Test get_type returns order_bump.
	 */
	public function test_get_type(): void {
		$this->assertEquals( 'order_bump', $this->field->get_type() );
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
		$this->assertArrayHasKey( 'order_bump_template', $defaults );
		$this->assertEquals( 'simple', $defaults['order_bump_template'] );
		$this->assertArrayHasKey( 'display_product_description', $defaults );
		$this->assertEquals( 0, $defaults['display_product_description'] );
	}

	/**
	 * Test default_fields returns array containing name.
	 */
	public function test_default_fields(): void {
		$fields = $this->field->default_fields();
		$this->assertIsArray( $fields );
		$this->assertContains( 'name', $fields );
	}

	/**
	 * Test force_attributes returns order_bump_template.
	 */
	public function test_force_attributes(): void {
		$attrs = $this->field->force_attributes();
		$this->assertIsArray( $attrs );
		$this->assertArrayHasKey( 'order_bump_template', $attrs );
		$this->assertEquals( 'simple', $attrs['order_bump_template'] );
	}

	/**
	 * Test get_fields returns array with expected keys.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'product', $fields );
		$this->assertArrayHasKey( 'display_product_description', $fields );
		$this->assertArrayHasKey( 'display_product_image', $fields );
	}

	/**
	 * Test get_fields product field is model type.
	 */
	public function test_get_fields_product_is_model_type(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'model', $fields['product']['type'] );
	}

	/**
	 * Test get_fields display_product_description is toggle type.
	 */
	public function test_get_fields_display_product_description_is_toggle(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'toggle', $fields['display_product_description']['type'] );
	}

	/**
	 * Test get_fields display_product_image is toggle type.
	 */
	public function test_get_fields_display_product_image_is_toggle(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'toggle', $fields['display_product_image']['type'] );
	}

	/**
	 * Test to_fields_array returns empty array when product not found.
	 */
	public function test_to_fields_array_returns_empty_when_no_product(): void {
		$attributes = array(
			'product'             => 999999,
			'order_bump_template' => 'simple',
			'id'                  => 'order_bump',
			'element_classes'     => '',
		);

		$result = $this->field->to_fields_array( $attributes );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test field is instance of Base_Signup_Field.
	 */
	public function test_field_is_instance_of_base(): void {
		$this->assertInstanceOf( Base_Signup_Field::class, $this->field );
	}
}

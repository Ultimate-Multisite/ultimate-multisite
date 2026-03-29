<?php
/**
 * Tests for Signup_Field_Products class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Products.
 */
class Signup_Field_Products_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Products
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Products();
	}

	/**
	 * Test get_type returns products.
	 */
	public function test_get_type(): void {
		$this->assertEquals( 'products', $this->field->get_type() );
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
	 * Test defaults returns an array.
	 */
	public function test_defaults(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray( $defaults );
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
	 * Test force_attributes returns id = products and name.
	 */
	public function test_force_attributes(): void {
		$attrs = $this->field->force_attributes();
		$this->assertIsArray( $attrs );
		$this->assertArrayHasKey( 'id', $attrs );
		$this->assertEquals( 'products', $attrs['id'] );
		$this->assertArrayHasKey( 'name', $attrs );
		$this->assertIsString( $attrs['name'] );
	}

	/**
	 * Test get_fields returns array with products key.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'products', $fields );
	}

	/**
	 * Test get_fields products is model type.
	 */
	public function test_get_fields_products_is_model_type(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'model', $fields['products']['type'] );
	}

	/**
	 * Test get_fields products has html_attr with data-model.
	 */
	public function test_get_fields_products_has_data_model_attr(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey( 'html_attr', $fields['products'] );
		$this->assertArrayHasKey( 'data-model', $fields['products']['html_attr'] );
		$this->assertEquals( 'product', $fields['products']['html_attr']['data-model'] );
	}

	/**
	 * Test to_fields_array returns hidden fields for each product.
	 */
	public function test_to_fields_array_returns_hidden_fields(): void {
		$attributes = array(
			'id'       => 'products',
			'name'     => 'Pre-selected Products',
			'products' => '1,2',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'products[1]', $fields );
		$this->assertArrayHasKey( 'products[2]', $fields );
	}

	/**
	 * Test to_fields_array hidden fields have correct type and value.
	 */
	public function test_to_fields_array_hidden_field_structure(): void {
		$attributes = array(
			'id'       => 'products',
			'name'     => 'Pre-selected Products',
			'products' => '42',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		$this->assertArrayHasKey( 'products[42]', $fields );
		$this->assertEquals( 'hidden', $fields['products[42]']['type'] );
		$this->assertEquals( '42', $fields['products[42]']['value'] );
	}

	/**
	 * Test field is instance of Base_Signup_Field.
	 */
	public function test_field_is_instance_of_base(): void {
		$this->assertInstanceOf( Base_Signup_Field::class, $this->field );
	}
}

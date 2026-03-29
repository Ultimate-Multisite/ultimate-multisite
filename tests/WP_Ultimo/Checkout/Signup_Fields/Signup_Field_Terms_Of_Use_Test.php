<?php
/**
 * Tests for Signup_Field_Terms_Of_Use class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Terms_Of_Use.
 */
class Signup_Field_Terms_Of_Use_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Terms_Of_Use
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Terms_Of_Use();
	}

	/**
	 * Test get_type returns terms_of_use.
	 */
	public function test_get_type(): void {
		$this->assertEquals( 'terms_of_use', $this->field->get_type() );
	}

	/**
	 * Test is_required returns false.
	 */
	public function test_is_required(): void {
		$this->assertFalse( $this->field->is_required() );
	}

	/**
	 * Test is_user_field returns false.
	 */
	public function test_is_user_field(): void {
		$this->assertFalse( $this->field->is_user_field() );
	}

	/**
	 * Test is_site_field returns false.
	 */
	public function test_is_site_field(): void {
		$this->assertFalse( $this->field->is_site_field() );
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
	 * Test defaults returns tou_name key with non-empty string.
	 */
	public function test_defaults(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'tou_name', $defaults );
		$this->assertIsString( $defaults['tou_name'] );
		$this->assertNotEmpty( $defaults['tou_name'] );
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
	 * Test force_attributes returns id = terms_of_use and name.
	 */
	public function test_force_attributes(): void {
		$attrs = $this->field->force_attributes();
		$this->assertIsArray( $attrs );
		$this->assertArrayHasKey( 'id', $attrs );
		$this->assertEquals( 'terms_of_use', $attrs['id'] );
		$this->assertArrayHasKey( 'name', $attrs );
		$this->assertIsString( $attrs['name'] );
	}

	/**
	 * Test get_fields returns array with tou_name and tou_url keys.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'tou_name', $fields );
		$this->assertArrayHasKey( 'tou_url', $fields );
	}

	/**
	 * Test get_fields tou_name is text type.
	 */
	public function test_get_fields_tou_name_is_text(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'text', $fields['tou_name']['type'] );
	}

	/**
	 * Test get_fields tou_url is url type.
	 */
	public function test_get_fields_tou_url_is_url(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'url', $fields['tou_url']['type'] );
	}

	/**
	 * Test to_fields_array returns terms_of_use checkbox field.
	 */
	public function test_to_fields_array_returns_checkbox(): void {
		$attributes = array(
			'id'              => 'terms_of_use',
			'name'            => 'Terms of Use',
			'tou_name'        => 'I agree with the terms of use.',
			'tou_url'         => 'https://example.com/terms',
			'element_classes' => '',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'terms_of_use', $fields );
		$this->assertEquals( 'checkbox', $fields['terms_of_use']['type'] );
	}

	/**
	 * Test to_fields_array checkbox is required.
	 */
	public function test_to_fields_array_checkbox_is_required(): void {
		$attributes = array(
			'id'              => 'terms_of_use',
			'name'            => 'Terms of Use',
			'tou_name'        => 'I agree with the terms of use.',
			'tou_url'         => 'https://example.com/terms',
			'element_classes' => '',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		$this->assertTrue( $fields['terms_of_use']['required'] );
	}

	/**
	 * Test to_fields_array desc contains tou_url link.
	 */
	public function test_to_fields_array_desc_contains_tou_url(): void {
		$attributes = array(
			'id'              => 'terms_of_use',
			'name'            => 'Terms of Use',
			'tou_name'        => 'I agree with the terms of use.',
			'tou_url'         => 'https://example.com/terms',
			'element_classes' => '',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		$this->assertStringContainsString( 'https://example.com/terms', $fields['terms_of_use']['desc'] );
	}

	/**
	 * Test to_fields_array name contains tou_name value.
	 */
	public function test_to_fields_array_name_contains_tou_name(): void {
		$attributes = array(
			'id'              => 'terms_of_use',
			'name'            => 'Terms of Use',
			'tou_name'        => 'I agree with the terms of use.',
			'tou_url'         => 'https://example.com/terms',
			'element_classes' => '',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		$this->assertStringContainsString( 'I agree with the terms of use.', $fields['terms_of_use']['name'] );
	}

	/**
	 * Test field is instance of Base_Signup_Field.
	 */
	public function test_field_is_instance_of_base(): void {
		$this->assertInstanceOf( Base_Signup_Field::class, $this->field );
	}
}

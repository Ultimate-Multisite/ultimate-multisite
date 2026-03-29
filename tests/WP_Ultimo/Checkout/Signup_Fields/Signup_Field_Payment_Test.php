<?php
/**
 * Tests for Signup_Field_Payment class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Payment.
 */
class Signup_Field_Payment_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Payment
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Payment();
	}

	/**
	 * Test get_type returns payment.
	 */
	public function test_get_type(): void {
		$this->assertEquals( 'payment', $this->field->get_type() );
	}

	/**
	 * Test is_required returns true.
	 */
	public function test_is_required(): void {
		$this->assertTrue( $this->field->is_required() );
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
	 * Test default_fields returns array containing name.
	 */
	public function test_default_fields(): void {
		$fields = $this->field->default_fields();
		$this->assertIsArray( $fields );
		$this->assertContains( 'name', $fields );
	}

	/**
	 * Test force_attributes returns id = payment.
	 */
	public function test_force_attributes(): void {
		$attrs = $this->field->force_attributes();
		$this->assertIsArray( $attrs );
		$this->assertArrayHasKey( 'id', $attrs );
		$this->assertEquals( 'payment', $attrs['id'] );
	}

	/**
	 * Test get_fields returns empty array.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray( $fields );
		$this->assertEmpty( $fields );
	}

	/**
	 * Test to_fields_array returns payment_template and payment keys.
	 */
	public function test_to_fields_array_contains_payment_keys(): void {
		$attributes = array(
			'id'                      => 'payment',
			'name'                    => 'Payment',
			'wrapper_element_classes' => '',
			'element_classes'         => '',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'payment_template', $fields );
		$this->assertArrayHasKey( 'payment', $fields );
	}

	/**
	 * Test to_fields_array payment_template is hidden text field.
	 */
	public function test_to_fields_array_payment_template_is_text(): void {
		$attributes = array(
			'id'                      => 'payment',
			'name'                    => 'Payment',
			'wrapper_element_classes' => '',
			'element_classes'         => '',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		$this->assertEquals( 'text', $fields['payment_template']['type'] );
		$this->assertStringContainsString( 'wu-hidden', $fields['payment_template']['classes'] );
	}

	/**
	 * Test to_fields_array payment field is payment-methods type.
	 */
	public function test_to_fields_array_payment_field_type(): void {
		$attributes = array(
			'id'                      => 'payment',
			'name'                    => 'Payment',
			'wrapper_element_classes' => '',
			'element_classes'         => '',
		);

		$this->field->set_attributes( $attributes );
		$fields = $this->field->to_fields_array( $attributes );

		$this->assertEquals( 'payment-methods', $fields['payment']['type'] );
	}

	/**
	 * Test field is instance of Base_Signup_Field.
	 */
	public function test_field_is_instance_of_base(): void {
		$this->assertInstanceOf( Base_Signup_Field::class, $this->field );
	}
}

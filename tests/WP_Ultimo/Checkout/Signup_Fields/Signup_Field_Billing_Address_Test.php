<?php
/**
 * Tests for Signup_Field_Billing_Address class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Billing_Address.
 */
class Signup_Field_Billing_Address_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Billing_Address
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Billing_Address();
	}

	/**
	 * Test get_type returns billing_address.
	 */
	public function test_get_type(): void {
		$this->assertEquals( 'billing_address', $this->field->get_type() );
	}

	/**
	 * Test is_required returns false.
	 */
	public function test_is_required(): void {
		$this->assertFalse( $this->field->is_required() );
	}

	/**
	 * Test is_user_field returns true.
	 */
	public function test_is_user_field(): void {
		$this->assertTrue( $this->field->is_user_field() );
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
	 * Test defaults returns array with zip_and_country key.
	 */
	public function test_defaults(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'zip_and_country', $defaults );
		$this->assertTrue( $defaults['zip_and_country'] );
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
	 * Test force_attributes returns id and required.
	 */
	public function test_force_attributes(): void {
		$attrs = $this->field->force_attributes();
		$this->assertIsArray( $attrs );
		$this->assertArrayHasKey( 'id', $attrs );
		$this->assertEquals( 'billing_address', $attrs['id'] );
		$this->assertArrayHasKey( 'required', $attrs );
		$this->assertTrue( $attrs['required'] );
	}

	/**
	 * Test get_fields returns array with zip_and_country key.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'zip_and_country', $fields );
	}

	/**
	 * Test get_fields zip_and_country is a toggle type.
	 */
	public function test_get_fields_zip_and_country_is_toggle(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'toggle', $fields['zip_and_country']['type'] );
	}

	/**
	 * Test build_select_alternative returns array with expected keys.
	 */
	public function test_build_select_alternative(): void {
		$base_field = array(
			'type'              => 'text',
			'title'             => 'State',
			'wrapper_html_attr' => array(),
			'html_attr'         => array(),
		);

		$result = $this->field->build_select_alternative( $base_field, 'state_list', 'state_field' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'select', $result['type'] );
		$this->assertArrayHasKey( 'options_template', $result );
		$this->assertArrayHasKey( 'options', $result );
		$this->assertTrue( $result['required'] );
	}

	/**
	 * Test build_select_alternative sets v-if on base_field.
	 */
	public function test_build_select_alternative_sets_v_if_on_base_field(): void {
		$base_field = array(
			'type'              => 'text',
			'title'             => 'State',
			'wrapper_html_attr' => array(),
			'html_attr'         => array(),
		);

		$this->field->build_select_alternative( $base_field, 'state_list', 'state_field' );

		$this->assertArrayHasKey( 'v-if', $base_field['wrapper_html_attr'] );
		$this->assertStringContainsString( 'state_list', $base_field['wrapper_html_attr']['v-if'] );
	}

	/**
	 * Test field is instance of Base_Signup_Field.
	 */
	public function test_field_is_instance_of_base(): void {
		$this->assertInstanceOf( Base_Signup_Field::class, $this->field );
	}
}

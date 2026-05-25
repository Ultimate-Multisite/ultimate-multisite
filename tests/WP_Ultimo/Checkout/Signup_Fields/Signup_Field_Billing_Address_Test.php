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
	 * Test defaults returns array with zip_and_country and required keys.
	 */
	public function test_defaults(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'zip_and_country', $defaults );
		$this->assertTrue( $defaults['zip_and_country'] );
		$this->assertArrayHasKey( 'required', $defaults );
		$this->assertTrue( $defaults['required'], 'Billing address must default to required for backwards-compatible behaviour.' );
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
	 * Test force_attributes returns id and does not force required.
	 *
	 * The `required` attribute is intentionally not forced so site owners can
	 * mark the billing address as optional via the editor toggle.
	 */
	public function test_force_attributes(): void {
		$attrs = $this->field->force_attributes();
		$this->assertIsArray( $attrs );
		$this->assertArrayHasKey( 'id', $attrs );
		$this->assertEquals( 'billing_address', $attrs['id'] );
		$this->assertArrayNotHasKey( 'required', $attrs, 'force_attributes should not pin required so the editor toggle can disable it.' );
	}

	/**
	 * Test get_fields returns array with zip_and_country and required keys.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray( $fields );
		$this->assertArrayHasKey( 'zip_and_country', $fields );
		$this->assertArrayHasKey( 'required', $fields );
	}

	/**
	 * Test get_fields zip_and_country is a toggle type.
	 */
	public function test_get_fields_zip_and_country_is_toggle(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'toggle', $fields['zip_and_country']['type'] );
	}

	/**
	 * Test get_fields required is a toggle defaulting to true.
	 */
	public function test_get_fields_required_is_toggle(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals( 'toggle', $fields['required']['type'] );
		$this->assertTrue( $fields['required']['value'] );
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
		$this->assertEquals( 'required', $result['html_attr']['required'] );
	}

	/**
	 * When the optional `$required` argument is false, the rebuilt select
	 * field must drop both its `required` flag and the HTML `required`
	 * attribute so the form can be submitted with no selection.
	 */
	public function test_build_select_alternative_optional(): void {
		$base_field = array(
			'type'              => 'text',
			'title'             => 'State',
			'wrapper_html_attr' => array(),
			'html_attr'         => array(),
		);

		$result = $this->field->build_select_alternative( $base_field, 'state_list', 'state_field', false );

		$this->assertFalse( $result['required'] );
		$this->assertArrayNotHasKey( 'required', $result['html_attr'] );
	}

	/**
	 * Regression guard: checkout forms saved before the "Address fields are
	 * required?" toggle existed have no `required` key in storage. The form
	 * render pipeline merges saved attributes with `defaults()` via
	 * `wp_parse_args` (`inc/functions/checkout.php:91`), so the missing key
	 * must always resolve to `true` for backwards compatibility.
	 *
	 * This test pins both layers of the invariant: `defaults()` returns
	 * `required => true`, AND `to_fields_array()` itself treats a missing
	 * `required` key as enabled (belt-and-braces in case a caller bypasses
	 * the wp_parse_args merge).
	 */
	public function test_required_defaults_to_enabled_for_legacy_attributes(): void {
		$defaults = $this->field->defaults();
		$this->assertArrayHasKey( 'required', $defaults );
		$this->assertTrue( $defaults['required'], 'defaults() must return required=true so wp_parse_args injects it into legacy saved attributes.' );

		// Simulate a legacy attribute payload (no `required` key) that
		// bypassed the form-render merge and was passed straight into the
		// signup field. The address fields must still be flagged required.
		$legacy_attributes = array(
			'id'              => 'billing_address',
			'zip_and_country' => true,
			'element_classes' => '',
		);

		$fields = $this->field->to_fields_array( $legacy_attributes );

		$this->assertArrayHasKey( 'billing_country', $fields );
		$this->assertTrue( ! empty( $fields['billing_country']['required'] ), 'Legacy forms must keep billing_country required.' );
		$this->assertArrayHasKey( 'billing_zip_code', $fields );
		$this->assertTrue( ! empty( $fields['billing_zip_code']['required'] ), 'Legacy forms must keep billing_zip_code required.' );
	}

	/**
	 * When the editor toggle is explicitly disabled, the emitted fields must
	 * drop their `required` flags so the form can be submitted with the
	 * address left blank.
	 */
	public function test_required_disabled_strips_required_flags(): void {
		$attributes = array(
			'id'              => 'billing_address',
			'zip_and_country' => true,
			'required'        => false,
			'element_classes' => '',
		);

		$fields = $this->field->to_fields_array( $attributes );

		$this->assertArrayHasKey( 'billing_country', $fields );
		$this->assertEmpty( $fields['billing_country']['required'] ?? false );
		$this->assertArrayHasKey( 'billing_zip_code', $fields );
		$this->assertEmpty( $fields['billing_zip_code']['required'] ?? false );
		if ( isset( $fields['billing_country']['html_attr'] ) ) {
			$this->assertArrayNotHasKey( 'required', $fields['billing_country']['html_attr'] );
		}
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

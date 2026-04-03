<?php
/**
 * Tests for Signup_Field_Password class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Password.
 */
class Signup_Field_Password_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Password
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Password();
	}

	/**
	 * Test get_type returns password.
	 */
	public function test_get_type(): void {
		$this->assertEquals('password', $this->field->get_type());
	}

	/**
	 * Test is_required returns true.
	 */
	public function test_is_required(): void {
		$this->assertTrue($this->field->is_required());
	}

	/**
	 * Test get_title returns string.
	 */
	public function test_get_title(): void {
		$title = $this->field->get_title();
		$this->assertIsString($title);
		$this->assertEquals('Password', $title);
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
	 * Test is_user_field returns true.
	 */
	public function test_is_user_field(): void {
		$this->assertTrue($this->field->is_user_field());
	}

	/**
	 * Test is_site_field returns false.
	 */
	public function test_is_site_field(): void {
		$this->assertFalse($this->field->is_site_field());
	}

	/**
	 * Test defaults returns array with expected keys.
	 */
	public function test_defaults(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray($defaults);
		$this->assertArrayHasKey('password_confirm_field', $defaults);
		$this->assertArrayHasKey('password_confirm_label', $defaults);
	}

	/**
	 * Test default_fields contains expected fields.
	 */
	public function test_default_fields(): void {
		$fields = $this->field->default_fields();
		$this->assertIsArray($fields);
		$this->assertContains('name', $fields);
		$this->assertContains('placeholder', $fields);
		$this->assertContains('tooltip', $fields);
	}

	/**
	 * Test force_attributes returns expected values.
	 */
	public function test_force_attributes(): void {
		$forced = $this->field->force_attributes();
		$this->assertIsArray($forced);
		$this->assertArrayHasKey('id', $forced);
		$this->assertEquals('password', $forced['id']);
		$this->assertArrayHasKey('required', $forced);
		$this->assertTrue($forced['required']);
	}

	/**
	 * Test get_fields returns array with expected keys.
	 */
	public function test_get_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray($fields);
		$this->assertArrayHasKey('password_strength_meter', $fields);
		$this->assertArrayHasKey('password_confirm_field', $fields);
	}

	/**
	 * Test to_fields_array returns empty when user is logged in.
	 */
	public function test_to_fields_array_logged_in_user(): void {
		// Create and log in a user
		$user_id = self::factory()->user->create();
		wp_set_current_user($user_id);

		$attributes = [
			'name'                    => 'Password',
			'placeholder'             => 'Enter password',
			'tooltip'                 => 'Your password',
			'password_strength_meter' => true,
			'password_confirm_field'  => false,
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertEmpty($fields);

		// Clean up
		wp_set_current_user(0);
	}

	/**
	 * Test to_fields_array returns password field when not logged in.
	 */
	public function test_to_fields_array_not_logged_in(): void {
		// Make sure user is not logged in
		wp_set_current_user(0);

		$attributes = [
			'name'                    => 'Password',
			'placeholder'             => 'Enter password',
			'tooltip'                 => 'Your password',
			'password_strength_meter' => true,
			'password_confirm_field'  => false,
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('password', $fields);
		$this->assertEquals('password', $fields['password']['type']);
		$this->assertEquals('Password', $fields['password']['name']);
	}

	/**
	 * Test to_fields_array includes confirm field when enabled.
	 */
	public function test_to_fields_array_with_confirm_field(): void {
		// Make sure user is not logged in
		wp_set_current_user(0);

		$attributes = [
			'name'                    => 'Password',
			'placeholder'             => 'Enter password',
			'tooltip'                 => 'Your password',
			'password_strength_meter' => true,
			'password_confirm_field'  => true,
			'password_confirm_label'  => 'Confirm Password',
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('password', $fields);
		$this->assertArrayHasKey('password_conf', $fields);
		$this->assertEquals('Confirm Password', $fields['password_conf']['name']);
	}

	/**
	 * Test defaults includes auto_generate_password key set to false.
	 */
	public function test_defaults_includes_auto_generate_password(): void {
		$defaults = $this->field->defaults();

		$this->assertArrayHasKey('auto_generate_password', $defaults);
		$this->assertFalse($defaults['auto_generate_password']);
	}

	/**
	 * Test get_fields includes auto_generate_password toggle.
	 */
	public function test_get_fields_includes_auto_generate_toggle(): void {
		$fields = $this->field->get_fields();

		$this->assertArrayHasKey('auto_generate_password', $fields);
		$this->assertEquals('toggle', $fields['auto_generate_password']['type']);
	}

	/**
	 * Test to_fields_array with auto_generate_password emits hidden flag only.
	 */
	public function test_to_fields_array_with_auto_generate_emits_hidden_flag(): void {
		wp_set_current_user(0);

		$attributes = [
			'name'                    => 'Password',
			'placeholder'             => '',
			'tooltip'                 => '',
			'auto_generate_password'  => true,
			'password_strength_meter' => false,
			'password_confirm_field'  => false,
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('auto_generate_password', $fields);
		$this->assertEquals('hidden', $fields['auto_generate_password']['type']);
		$this->assertEquals('1', $fields['auto_generate_password']['value']);
		$this->assertArrayNotHasKey('password', $fields);
	}

	/**
	 * Test to_fields_array with auto_generate_password does not render visible password field.
	 */
	public function test_to_fields_array_auto_generate_no_visible_password(): void {
		wp_set_current_user(0);

		$attributes = [
			'name'                   => 'Password',
			'placeholder'            => '',
			'tooltip'                => '',
			'auto_generate_password' => true,
		];

		$this->field->set_attributes($attributes);
		$fields = $this->field->to_fields_array($attributes);

		$this->assertArrayNotHasKey('password', $fields);
		$this->assertArrayNotHasKey('password_conf', $fields);
	}

	/**
	 * Test password_strength_meter field has v-show guard when auto_generate is on.
	 */
	public function test_password_strength_meter_has_v_show_guard(): void {
		$fields = $this->field->get_fields();

		$this->assertArrayHasKey('password_strength_meter', $fields);
		$this->assertArrayHasKey('wrapper_html_attr', $fields['password_strength_meter']);
		$this->assertArrayHasKey('v-show', $fields['password_strength_meter']['wrapper_html_attr']);
		$this->assertEquals('!auto_generate_password', $fields['password_strength_meter']['wrapper_html_attr']['v-show']);
	}

	/**
	 * Test password_confirm_field has v-show guard when auto_generate is on.
	 */
	public function test_password_confirm_field_has_v_show_guard(): void {
		$fields = $this->field->get_fields();

		$this->assertArrayHasKey('password_confirm_field', $fields);
		$this->assertArrayHasKey('wrapper_html_attr', $fields['password_confirm_field']);
		$this->assertArrayHasKey('v-show', $fields['password_confirm_field']['wrapper_html_attr']);
		$this->assertEquals('!auto_generate_password', $fields['password_confirm_field']['wrapper_html_attr']['v-show']);
	}
}

<?php
/**
 * Tests for Signup_Field_Period_Selection class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;

/**
 * Test class for Signup_Field_Period_Selection.
 */
class Signup_Field_Period_Selection_Test extends WP_UnitTestCase {

	/**
	 * @var Signup_Field_Period_Selection
	 */
	private $field;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->field = new Signup_Field_Period_Selection();
	}

	/**
	 * Tear down: dequeue/deregister scripts and styles registered during tests.
	 */
	protected function tearDown(): void {
		wp_dequeue_script('wu-legacy-signup');
		wp_deregister_script('wu-legacy-signup');
		wp_dequeue_style('legacy-shortcodes');
		wp_deregister_style('legacy-shortcodes');
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Identity / metadata methods
	// -------------------------------------------------------------------------

	/**
	 * Test get_type returns period_selection.
	 */
	public function test_get_type(): void {
		$this->assertEquals('period_selection', $this->field->get_type());
	}

	/**
	 * Test is_required returns false.
	 */
	public function test_is_required(): void {
		$this->assertFalse($this->field->is_required());
	}

	/**
	 * Test get_title returns non-empty string.
	 */
	public function test_get_title(): void {
		$title = $this->field->get_title();
		$this->assertIsString($title);
		$this->assertNotEmpty($title);
	}

	/**
	 * Test get_description returns non-empty string.
	 */
	public function test_get_description(): void {
		$description = $this->field->get_description();
		$this->assertIsString($description);
		$this->assertNotEmpty($description);
	}

	/**
	 * Test get_tooltip returns non-empty string.
	 */
	public function test_get_tooltip(): void {
		$tooltip = $this->field->get_tooltip();
		$this->assertIsString($tooltip);
		$this->assertNotEmpty($tooltip);
	}

	/**
	 * Test get_icon returns a dashicons string.
	 */
	public function test_get_icon(): void {
		$icon = $this->field->get_icon();
		$this->assertIsString($icon);
		$this->assertStringContainsString('dashicons', $icon);
	}

	/**
	 * Test description and tooltip contain the same text (both describe the period selector).
	 */
	public function test_description_equals_tooltip(): void {
		$this->assertEquals($this->field->get_description(), $this->field->get_tooltip());
	}

	// -------------------------------------------------------------------------
	// Inherited base-class behaviour
	// -------------------------------------------------------------------------

	/**
	 * Test is_user_field returns false (inherited default).
	 */
	public function test_is_user_field(): void {
		$this->assertFalse($this->field->is_user_field());
	}

	/**
	 * Test is_site_field returns false (inherited default).
	 */
	public function test_is_site_field(): void {
		$this->assertFalse($this->field->is_site_field());
	}

	/**
	 * Test is_hidden returns false (inherited default).
	 */
	public function test_is_hidden(): void {
		$this->assertFalse($this->field->is_hidden());
	}

	/**
	 * Test field extends Base_Signup_Field.
	 */
	public function test_inheritance(): void {
		$this->assertInstanceOf(Base_Signup_Field::class, $this->field);
	}

	// -------------------------------------------------------------------------
	// defaults()
	// -------------------------------------------------------------------------

	/**
	 * Test defaults returns an array.
	 */
	public function test_defaults_returns_array(): void {
		$defaults = $this->field->defaults();
		$this->assertIsArray($defaults);
	}

	/**
	 * Test defaults contains period_selection_template key.
	 */
	public function test_defaults_has_period_selection_template(): void {
		$defaults = $this->field->defaults();
		$this->assertArrayHasKey('period_selection_template', $defaults);
	}

	/**
	 * Test defaults period_selection_template value is 'clean'.
	 */
	public function test_defaults_period_selection_template_is_clean(): void {
		$defaults = $this->field->defaults();
		$this->assertEquals('clean', $defaults['period_selection_template']);
	}

	// -------------------------------------------------------------------------
	// default_fields()
	// -------------------------------------------------------------------------

	/**
	 * Test default_fields returns an array.
	 */
	public function test_default_fields_returns_array(): void {
		$fields = $this->field->default_fields();
		$this->assertIsArray($fields);
	}

	/**
	 * Test default_fields returns empty array (name is commented out).
	 */
	public function test_default_fields_is_empty(): void {
		$fields = $this->field->default_fields();
		$this->assertEmpty($fields);
	}

	// -------------------------------------------------------------------------
	// force_attributes()
	// -------------------------------------------------------------------------

	/**
	 * Test force_attributes returns an array.
	 */
	public function test_force_attributes_returns_array(): void {
		$forced = $this->field->force_attributes();
		$this->assertIsArray($forced);
	}

	/**
	 * Test force_attributes contains id key with value period_selection.
	 */
	public function test_force_attributes_id(): void {
		$forced = $this->field->force_attributes();
		$this->assertArrayHasKey('id', $forced);
		$this->assertEquals('period_selection', $forced['id']);
	}

	/**
	 * Test force_attributes contains name key.
	 */
	public function test_force_attributes_name(): void {
		$forced = $this->field->force_attributes();
		$this->assertArrayHasKey('name', $forced);
		$this->assertIsString($forced['name']);
		$this->assertNotEmpty($forced['name']);
	}

	/**
	 * Test force_attributes required is true.
	 */
	public function test_force_attributes_required_is_true(): void {
		$forced = $this->field->force_attributes();
		$this->assertArrayHasKey('required', $forced);
		$this->assertTrue($forced['required']);
	}

	// -------------------------------------------------------------------------
	// get_template_options()
	// -------------------------------------------------------------------------

	/**
	 * Test get_template_options returns an array.
	 */
	public function test_get_template_options_returns_array(): void {
		$options = $this->field->get_template_options();
		$this->assertIsArray($options);
	}

	/**
	 * Test get_template_options contains at least one entry.
	 */
	public function test_get_template_options_not_empty(): void {
		$options = $this->field->get_template_options();
		$this->assertNotEmpty($options);
	}

	/**
	 * Test get_template_options contains 'clean' key.
	 */
	public function test_get_template_options_has_clean(): void {
		$options = $this->field->get_template_options();
		$this->assertArrayHasKey('clean', $options);
	}

	/**
	 * Test get_template_options contains 'legacy' key.
	 */
	public function test_get_template_options_has_legacy(): void {
		$options = $this->field->get_template_options();
		$this->assertArrayHasKey('legacy', $options);
	}

	// -------------------------------------------------------------------------
	// get_fields()
	// -------------------------------------------------------------------------

	/**
	 * Test get_fields returns an array.
	 */
	public function test_get_fields_returns_array(): void {
		$fields = $this->field->get_fields();
		$this->assertIsArray($fields);
	}

	/**
	 * Test get_fields contains period_selection_template group.
	 */
	public function test_get_fields_has_period_selection_template(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('period_selection_template', $fields);
	}

	/**
	 * Test period_selection_template field is a group type.
	 */
	public function test_get_fields_template_is_group(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals('group', $fields['period_selection_template']['type']);
	}

	/**
	 * Test period_selection_template group has nested fields.
	 */
	public function test_get_fields_template_group_has_fields(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('fields', $fields['period_selection_template']);
		$this->assertIsArray($fields['period_selection_template']['fields']);
	}

	/**
	 * Test period_selection_template nested field is a select.
	 */
	public function test_get_fields_template_nested_select(): void {
		$fields  = $this->field->get_fields();
		$nested  = $fields['period_selection_template']['fields'];
		$this->assertArrayHasKey('period_selection_template', $nested);
		$this->assertEquals('select', $nested['period_selection_template']['type']);
	}

	/**
	 * Test get_fields contains period_options_header.
	 */
	public function test_get_fields_has_period_options_header(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('period_options_header', $fields);
	}

	/**
	 * Test period_options_header is a small-header type.
	 */
	public function test_get_fields_period_options_header_type(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals('small-header', $fields['period_options_header']['type']);
	}

	/**
	 * Test get_fields contains period_options_empty.
	 */
	public function test_get_fields_has_period_options_empty(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('period_options_empty', $fields);
	}

	/**
	 * Test period_options_empty is a note type.
	 */
	public function test_get_fields_period_options_empty_type(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals('note', $fields['period_options_empty']['type']);
	}

	/**
	 * Test get_fields contains period_options group.
	 */
	public function test_get_fields_has_period_options(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('period_options', $fields);
	}

	/**
	 * Test period_options is a group type.
	 */
	public function test_get_fields_period_options_is_group(): void {
		$fields = $this->field->get_fields();
		$this->assertEquals('group', $fields['period_options']['type']);
	}

	/**
	 * Test period_options group has nested fields.
	 */
	public function test_get_fields_period_options_has_nested_fields(): void {
		$fields  = $this->field->get_fields();
		$this->assertArrayHasKey('fields', $fields['period_options']);
		$nested = $fields['period_options']['fields'];
		$this->assertArrayHasKey('period_options_remove', $nested);
		$this->assertArrayHasKey('period_options_duration', $nested);
		$this->assertArrayHasKey('period_options_duration_unit', $nested);
		$this->assertArrayHasKey('period_options_label', $nested);
	}

	/**
	 * Test period_options_duration is a number field.
	 */
	public function test_get_fields_duration_is_number(): void {
		$fields = $this->field->get_fields();
		$nested = $fields['period_options']['fields'];
		$this->assertEquals('number', $nested['period_options_duration']['type']);
	}

	/**
	 * Test period_options_duration_unit is a select field.
	 */
	public function test_get_fields_duration_unit_is_select(): void {
		$fields = $this->field->get_fields();
		$nested = $fields['period_options']['fields'];
		$this->assertEquals('select', $nested['period_options_duration_unit']['type']);
	}

	/**
	 * Test period_options_duration_unit options contain expected keys.
	 */
	public function test_get_fields_duration_unit_options(): void {
		$fields   = $this->field->get_fields();
		$nested   = $fields['period_options']['fields'];
		$options  = $nested['period_options_duration_unit']['options'];
		$this->assertArrayHasKey('day', $options);
		$this->assertArrayHasKey('week', $options);
		$this->assertArrayHasKey('month', $options);
		$this->assertArrayHasKey('year', $options);
	}

	/**
	 * Test period_options_label is a text field.
	 */
	public function test_get_fields_label_is_text(): void {
		$fields = $this->field->get_fields();
		$nested = $fields['period_options']['fields'];
		$this->assertEquals('text', $nested['period_options_label']['type']);
	}

	/**
	 * Test get_fields contains repeat submit button.
	 */
	public function test_get_fields_has_repeat(): void {
		$fields = $this->field->get_fields();
		$this->assertArrayHasKey('repeat', $fields);
		$this->assertEquals('submit', $fields['repeat']['type']);
	}

	/**
	 * Test get_fields order values are numeric.
	 */
	public function test_get_fields_order_values(): void {
		$fields = $this->field->get_fields();
		$this->assertIsNumeric($fields['period_options_header']['order']);
		$this->assertIsNumeric($fields['period_options']['order']);
		$this->assertIsNumeric($fields['repeat']['order']);
	}

	// -------------------------------------------------------------------------
	// to_fields_array() — non-legacy template
	// -------------------------------------------------------------------------

	/**
	 * Build a minimal valid attributes array for to_fields_array().
	 *
	 * @return array
	 */
	private function make_attributes( string $template = 'clean' ): array {
		return [
			'id'                        => 'period_selection',
			'element_classes'           => 'wu-period-selection',
			'period_selection_template' => $template,
			'period_options'            => [],
		];
	}

	/**
	 * Test to_fields_array returns an array.
	 */
	public function test_to_fields_array_returns_array(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertIsArray($fields);
	}

	/**
	 * Test to_fields_array contains the period_selection note field.
	 */
	public function test_to_fields_array_has_period_selection_note(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertArrayHasKey('period_selection', $fields);
		$this->assertEquals('note', $fields['period_selection']['type']);
	}

	/**
	 * Test to_fields_array note field has correct id.
	 */
	public function test_to_fields_array_note_id(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertEquals('period_selection', $fields['period_selection']['id']);
	}

	/**
	 * Test to_fields_array note field has wrapper_classes from attributes.
	 */
	public function test_to_fields_array_note_wrapper_classes(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertEquals('wu-period-selection', $fields['period_selection']['wrapper_classes']);
	}

	/**
	 * Test to_fields_array note field desc is callable.
	 */
	public function test_to_fields_array_note_desc_is_callable(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertIsCallable($fields['period_selection']['desc']);
	}

	/**
	 * Test to_fields_array contains duration hidden field.
	 */
	public function test_to_fields_array_has_duration(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertArrayHasKey('duration', $fields);
		$this->assertEquals('hidden', $fields['duration']['type']);
	}

	/**
	 * Test to_fields_array duration field has v-model html_attr.
	 */
	public function test_to_fields_array_duration_v_model(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertArrayHasKey('html_attr', $fields['duration']);
		$this->assertEquals('duration', $fields['duration']['html_attr']['v-model']);
	}

	/**
	 * Test to_fields_array contains duration_unit hidden field.
	 */
	public function test_to_fields_array_has_duration_unit(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertArrayHasKey('duration_unit', $fields);
		$this->assertEquals('hidden', $fields['duration_unit']['type']);
	}

	/**
	 * Test to_fields_array duration_unit field has v-model html_attr.
	 */
	public function test_to_fields_array_duration_unit_v_model(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertArrayHasKey('html_attr', $fields['duration_unit']);
		$this->assertEquals('duration_unit', $fields['duration_unit']['html_attr']['v-model']);
	}

	/**
	 * Test to_fields_array returns exactly 3 keys.
	 */
	public function test_to_fields_array_key_count(): void {
		$attributes = $this->make_attributes();
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertCount(3, $fields);
	}

	// -------------------------------------------------------------------------
	// to_fields_array() — legacy template (enqueues scripts/styles)
	// -------------------------------------------------------------------------

	/**
	 * Test to_fields_array with legacy template still returns array with 3 keys.
	 */
	public function test_to_fields_array_legacy_returns_array(): void {
		$attributes = $this->make_attributes('legacy');
		$fields     = $this->field->to_fields_array($attributes);
		$this->assertIsArray($fields);
		$this->assertCount(3, $fields);
	}

	/**
	 * Test to_fields_array with legacy template enqueues wu-legacy-signup script.
	 */
	public function test_to_fields_array_legacy_enqueues_script(): void {
		$attributes = $this->make_attributes('legacy');
		$this->field->to_fields_array($attributes);
		$this->assertTrue(wp_script_is('wu-legacy-signup', 'enqueued'));
	}

	/**
	 * Test to_fields_array with legacy template enqueues legacy-shortcodes style.
	 */
	public function test_to_fields_array_legacy_enqueues_style(): void {
		$attributes = $this->make_attributes('legacy');
		$this->field->to_fields_array($attributes);
		$this->assertTrue(wp_style_is('legacy-shortcodes', 'enqueued'));
	}

	/**
	 * Test to_fields_array with non-legacy template does NOT enqueue wu-legacy-signup.
	 */
	public function test_to_fields_array_clean_does_not_enqueue_legacy_script(): void {
		$attributes = $this->make_attributes('clean');
		$this->field->to_fields_array($attributes);
		$this->assertFalse(wp_script_is('wu-legacy-signup', 'enqueued'));
	}

	// -------------------------------------------------------------------------
	// to_fields_array() — desc closure behaviour
	// -------------------------------------------------------------------------

	/**
	 * Test desc closure renders template output when template class exists.
	 */
	public function test_to_fields_array_desc_renders_template(): void {
		$attributes = $this->make_attributes('clean');
		$fields     = $this->field->to_fields_array($attributes);

		$desc = $fields['period_selection']['desc'];

		// Capture output — template may produce HTML or nothing; we just assert it is callable
		ob_start();
		$desc();
		$output = ob_get_clean();

		// The closure ran without throwing; output may be empty string or HTML
		$this->assertIsString($output);
	}

	/**
	 * Test desc closure outputs fallback message when template does not exist.
	 */
	public function test_to_fields_array_desc_fallback_for_unknown_template(): void {
		$attributes = $this->make_attributes('nonexistent_template_xyz');
		$fields     = $this->field->to_fields_array($attributes);

		$desc = $fields['period_selection']['desc'];

		ob_start();
		$desc();
		$output = ob_get_clean();

		$this->assertStringContainsString('Template does not exist', $output);
	}

	// -------------------------------------------------------------------------
	// get_field_as_type_option() — inherited method exercised via period_selection
	// -------------------------------------------------------------------------

	/**
	 * Test get_field_as_type_option returns expected keys.
	 */
	public function test_get_field_as_type_option(): void {
		$option = $this->field->get_field_as_type_option();

		$this->assertIsArray($option);
		$this->assertArrayHasKey('title', $option);
		$this->assertArrayHasKey('desc', $option);
		$this->assertArrayHasKey('tooltip', $option);
		$this->assertArrayHasKey('type', $option);
		$this->assertArrayHasKey('icon', $option);
		$this->assertArrayHasKey('required', $option);
		$this->assertArrayHasKey('default_fields', $option);
		$this->assertArrayHasKey('force_attributes', $option);
		$this->assertArrayHasKey('all_attributes', $option);
		$this->assertArrayHasKey('fields', $option);
	}

	/**
	 * Test get_field_as_type_option type matches get_type().
	 */
	public function test_get_field_as_type_option_type(): void {
		$option = $this->field->get_field_as_type_option();
		$this->assertEquals('period_selection', $option['type']);
	}

	/**
	 * Test get_field_as_type_option required matches is_required().
	 */
	public function test_get_field_as_type_option_required(): void {
		$option = $this->field->get_field_as_type_option();
		$this->assertFalse($option['required']);
	}

	// -------------------------------------------------------------------------
	// get_all_attributes() — inherited method
	// -------------------------------------------------------------------------

	/**
	 * Test get_all_attributes returns an array.
	 */
	public function test_get_all_attributes_returns_array(): void {
		$attrs = $this->field->get_all_attributes();
		$this->assertIsArray($attrs);
	}

	/**
	 * Test get_all_attributes includes period_selection_template.
	 */
	public function test_get_all_attributes_includes_template_key(): void {
		$attrs = $this->field->get_all_attributes();
		$this->assertContains('period_selection_template', $attrs);
	}

	// -------------------------------------------------------------------------
	// set_attributes() / calculate_style_attr() — inherited methods
	// -------------------------------------------------------------------------

	/**
	 * Test set_attributes stores the attributes.
	 */
	public function test_set_attributes(): void {
		$data = ['id' => 'period_selection', 'width' => 50];
		$this->field->set_attributes($data);

		$reflection = new \ReflectionClass($this->field);
		$prop       = $reflection->getProperty('attributes');

		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

		$this->assertEquals($data, $prop->getValue($this->field));
	}

	/**
	 * Test calculate_style_attr with width 50 returns float left style.
	 */
	public function test_calculate_style_attr_with_width(): void {
		$this->field->set_attributes(['width' => 50]);
		$style = $this->field->calculate_style_attr();
		$this->assertStringContainsString('float: left', $style);
		$this->assertStringContainsString('50%', $style);
	}

	/**
	 * Test calculate_style_attr with width 100 returns empty string (no float, no clear).
	 *
	 * When width === 100, the code skips the float/width styles but does NOT add
	 * clear:both (that only happens when width is falsy/0).
	 */
	public function test_calculate_style_attr_width_100(): void {
		$this->field->set_attributes(['width' => 100]);
		$style = $this->field->calculate_style_attr();
		// Width 100: no float/width added, no clear:both — result is empty
		$this->assertSame('', $style);
	}

	/**
	 * Test calculate_style_attr with no width returns clear both.
	 */
	public function test_calculate_style_attr_no_width(): void {
		$this->field->set_attributes([]);
		$style = $this->field->calculate_style_attr();
		$this->assertStringContainsString('clear: both', $style);
	}

	// -------------------------------------------------------------------------
	// reduce_attributes() — inherited pass-through
	// -------------------------------------------------------------------------

	/**
	 * Test reduce_attributes returns the same array unchanged.
	 */
	public function test_reduce_attributes(): void {
		$data   = ['foo' => 'bar', 'baz' => 123];
		$result = $this->field->reduce_attributes($data);
		$this->assertEquals($data, $result);
	}

	// -------------------------------------------------------------------------
	// get_tabs() — inherited method
	// -------------------------------------------------------------------------

	/**
	 * Test get_tabs returns array with content and style.
	 */
	public function test_get_tabs(): void {
		$tabs = $this->field->get_tabs();
		$this->assertIsArray($tabs);
		$this->assertContains('content', $tabs);
		$this->assertContains('style', $tabs);
	}
}

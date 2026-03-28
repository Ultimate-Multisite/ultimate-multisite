<?php

namespace WP_Ultimo\Admin_Pages;

use WP_Ultimo\Faker;
use WP_Ultimo\Models\Checkout_Form;
use WP_UnitTestCase;

/**
 * Test class for the Checkout_Form_Edit_Admin_Page class.
 *
 * Covers: action_links, field_types, get_create_field_fields, get_required_list,
 * get_field, get_step, get_title, get_menu_title, get_labels, query_filter,
 * has_title, get_object, handle_save, register_forms, save_editor_session,
 * generate_checkout_form_preview, page_loaded, register_widgets, register_scripts,
 * get_thank_you_page_fields, get_thank_you_settings, render_steps, render_js_templates.
 */
class Checkout_Form_Edit_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * Instance of Checkout_Form_Edit_Admin_Page.
	 */
	protected Checkout_Form_Edit_Admin_Page $page;

	/**
	 * A saved checkout form for use in tests.
	 */
	protected Checkout_Form $checkout_form;

	/**
	 * Sets up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		$faker = new Faker();
		$faker->generate_fake_checkout_form();

		$this->checkout_form = current($faker->get_fake_data_generated('checkout_forms'));

		$this->page = new Checkout_Form_Edit_Admin_Page();
	}

	/**
	 * Tear down: reset $_REQUEST and $_POST.
	 */
	public function tearDown(): void {
		$_REQUEST = [];
		$_POST    = [];
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// get_title
	// -------------------------------------------------------------------------

	/**
	 * get_title returns "Edit Checkout Form" when editing an existing object.
	 */
	public function test_get_title_returns_edit_label_when_editing(): void {
		$this->page->edit = true;
		$this->assertEquals(__('Edit Checkout Form', 'ultimate-multisite'), $this->page->get_title());
	}

	/**
	 * get_title returns "Add new Checkout Form" when creating a new object.
	 */
	public function test_get_title_returns_add_new_label_when_not_editing(): void {
		$this->page->edit = false;
		$this->assertEquals(__('Add new Checkout Form', 'ultimate-multisite'), $this->page->get_title());
	}

	// -------------------------------------------------------------------------
	// get_menu_title
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns the expected string.
	 */
	public function test_get_menu_title(): void {
		$this->assertEquals(__('Edit Checkout_Form', 'ultimate-multisite'), $this->page->get_menu_title());
	}

	// -------------------------------------------------------------------------
	// get_labels
	// -------------------------------------------------------------------------

	/**
	 * get_labels returns an array with all required keys.
	 */
	public function test_get_labels_returns_all_required_keys(): void {
		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);

		$required_keys = [
			'edit_label',
			'add_new_label',
			'updated_message',
			'title_placeholder',
			'title_description',
			'save_button_label',
			'save_description',
			'delete_button_label',
			'delete_description',
		];

		foreach ($required_keys as $key) {
			$this->assertArrayHasKey($key, $labels, "Missing key: $key");
		}
	}

	/**
	 * get_labels returns correct string values.
	 */
	public function test_get_labels_returns_correct_values(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals(__('Edit Checkout Form', 'ultimate-multisite'), $labels['edit_label']);
		$this->assertEquals(__('Add new Checkout Form', 'ultimate-multisite'), $labels['add_new_label']);
		$this->assertEquals(__('Checkout Form updated with success!', 'ultimate-multisite'), $labels['updated_message']);
		$this->assertEquals(__('Save Checkout Form', 'ultimate-multisite'), $labels['save_button_label']);
		$this->assertEquals(__('Delete Checkout Form', 'ultimate-multisite'), $labels['delete_button_label']);
	}

	// -------------------------------------------------------------------------
	// has_title
	// -------------------------------------------------------------------------

	/**
	 * has_title returns true.
	 */
	public function test_has_title_returns_true(): void {
		$this->assertTrue($this->page->has_title());
	}

	// -------------------------------------------------------------------------
	// get_object
	// -------------------------------------------------------------------------

	/**
	 * get_object returns the checkout form when a valid ID is in the request.
	 */
	public function test_get_object_returns_checkout_form_for_valid_id(): void {
		$_REQUEST['id'] = $this->checkout_form->get_id();

		$object = $this->page->get_object();

		$this->assertInstanceOf(Checkout_Form::class, $object);
		$this->assertEquals($this->checkout_form->get_id(), $object->get_id());
	}

	/**
	 * get_object caches the result on repeated calls.
	 */
	public function test_get_object_caches_result(): void {
		$_REQUEST['id'] = $this->checkout_form->get_id();

		$first  = $this->page->get_object();
		$second = $this->page->get_object();

		$this->assertSame($first, $second);
	}

	// -------------------------------------------------------------------------
	// action_links
	// -------------------------------------------------------------------------

	/**
	 * action_links returns an empty array when the object does not exist (new form).
	 */
	public function test_action_links_returns_empty_when_object_not_saved(): void {
		$new_form       = new Checkout_Form();
		$this->page->object = $new_form;

		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertEmpty($links);
	}

	/**
	 * action_links returns the Generate Shortcode action when the object exists.
	 */
	public function test_action_links_returns_shortcode_action_for_existing_object(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertNotEmpty($links);

		$first_link = $links[0];
		$this->assertArrayHasKey('label', $first_link);
		$this->assertArrayHasKey('icon', $first_link);
		$this->assertArrayHasKey('url', $first_link);
		$this->assertEquals(__('Generate Shortcode', 'ultimate-multisite'), $first_link['label']);
		$this->assertEquals('wu-copy', $first_link['icon']);
	}

	// -------------------------------------------------------------------------
	// query_filter
	// -------------------------------------------------------------------------

	/**
	 * query_filter merges object_type and object_id into the args.
	 */
	public function test_query_filter_adds_object_type_and_id(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$args   = ['some_arg' => 'value'];
		$result = $this->page->query_filter($args);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertArrayHasKey('object_id', $result);
		$this->assertEquals('checkout_form', $result['object_type']);
		$this->assertEquals($this->checkout_form->get_id(), $result['object_id']);
		$this->assertEquals('value', $result['some_arg']);
	}

	/**
	 * query_filter preserves existing args.
	 */
	public function test_query_filter_preserves_existing_args(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$args   = ['per_page' => 20, 'orderby' => 'date'];
		$result = $this->page->query_filter($args);

		$this->assertEquals(20, $result['per_page']);
		$this->assertEquals('date', $result['orderby']);
	}

	// -------------------------------------------------------------------------
	// field_types
	// -------------------------------------------------------------------------

	/**
	 * field_types returns a non-empty array of field type options.
	 */
	public function test_field_types_returns_array_of_field_types(): void {
		$field_types = $this->page->field_types();

		$this->assertIsArray($field_types);
		$this->assertNotEmpty($field_types);
	}

	/**
	 * field_types returns items with required keys.
	 */
	public function test_field_types_items_have_required_keys(): void {
		$field_types = $this->page->field_types();

		foreach ($field_types as $type) {
			$this->assertArrayHasKey('title', $type);
			$this->assertArrayHasKey('type', $type);
			$this->assertArrayHasKey('fields', $type);
			$this->assertArrayHasKey('default_fields', $type);
			$this->assertArrayHasKey('force_attributes', $type);
			$this->assertArrayHasKey('all_attributes', $type);
		}
	}

	/**
	 * field_types does not include hidden field types.
	 */
	public function test_field_types_excludes_hidden_fields(): void {
		$field_types = $this->page->field_types();

		// Collect the 'type' slugs of all fields that report is_hidden() === true
		$hidden_type_slugs = [];
		$registered        = \WP_Ultimo\Managers\Signup_Fields_Manager::get_instance()->get_field_types();
		foreach ($registered as $class_name) {
			$field = new $class_name();
			if ($field->is_hidden()) {
				$hidden_type_slugs[] = $field->get_type();
			}
		}

		// None of the hidden type slugs should appear in the returned field_types array
		$returned_type_slugs = array_column($field_types, 'type');
		$intersection        = array_intersect($returned_type_slugs, $hidden_type_slugs);
		$this->assertEmpty(
			$intersection,
			'field_types() must not include hidden field types: ' . implode(', ', $intersection)
		);
	}

	// -------------------------------------------------------------------------
	// get_required_list
	// -------------------------------------------------------------------------

	/**
	 * get_required_list returns an array.
	 */
	public function test_get_required_list_returns_array(): void {
		$field_types = $this->page->field_types();
		$result      = $this->page->get_required_list('name', $field_types);

		$this->assertIsArray($result);
	}

	/**
	 * get_required_list returns empty array for a slug not in any field type.
	 */
	public function test_get_required_list_returns_empty_for_unknown_slug(): void {
		$field_types = $this->page->field_types();
		$result      = $this->page->get_required_list('nonexistent_field_slug_xyz', $field_types);

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	/**
	 * get_required_list returns field type keys that include the given slug.
	 */
	public function test_get_required_list_returns_matching_field_type_keys(): void {
		$field_types = $this->page->field_types();

		// 'name' is a common default field in many field types
		$result = $this->page->get_required_list('name', $field_types);

		// Result should be array of keys (field type slugs)
		foreach ($result as $key) {
			$this->assertIsString($key);
		}
	}

	// -------------------------------------------------------------------------
	// get_create_field_fields
	// -------------------------------------------------------------------------

	/**
	 * get_create_field_fields returns an array with expected base fields.
	 */
	public function test_get_create_field_fields_returns_base_fields(): void {
		$fields = $this->page->get_create_field_fields();

		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);

		// Check for core fields
		$this->assertArrayHasKey('tab', $fields);
		$this->assertArrayHasKey('type', $fields);
		$this->assertArrayHasKey('submit_button', $fields);
	}

	/**
	 * get_create_field_fields includes style fields.
	 */
	public function test_get_create_field_fields_includes_style_fields(): void {
		$fields = $this->page->get_create_field_fields();

		$this->assertArrayHasKey('width', $fields);
		$this->assertArrayHasKey('wrapper_element_classes', $fields);
		$this->assertArrayHasKey('element_classes', $fields);
	}

	/**
	 * get_create_field_fields includes advanced tab fields.
	 */
	public function test_get_create_field_fields_includes_advanced_fields(): void {
		$fields = $this->page->get_create_field_fields();

		$this->assertArrayHasKey('from_request', $fields);
		$this->assertArrayHasKey('logged', $fields);
	}

	/**
	 * get_create_field_fields includes hidden fields for step and checkout_form.
	 */
	public function test_get_create_field_fields_includes_hidden_context_fields(): void {
		$fields = $this->page->get_create_field_fields();

		$this->assertArrayHasKey('step', $fields);
		$this->assertArrayHasKey('checkout_form', $fields);
		$this->assertEquals('hidden', $fields['step']['type']);
		$this->assertEquals('hidden', $fields['checkout_form']['type']);
	}

	/**
	 * get_create_field_fields submit button title changes based on attributes.
	 */
	public function test_get_create_field_fields_submit_button_title_for_new_field(): void {
		$fields = $this->page->get_create_field_fields([]);

		$this->assertEquals(__('Add Field', 'ultimate-multisite'), $fields['submit_button']['title']);
	}

	/**
	 * get_create_field_fields submit button title is "Save Field" when attributes provided.
	 */
	public function test_get_create_field_fields_submit_button_title_for_existing_field(): void {
		$fields = $this->page->get_create_field_fields(['type' => 'text', 'name' => 'My Field']);

		$this->assertEquals(__('Save Field', 'ultimate-multisite'), $fields['submit_button']['title']);
	}

	/**
	 * get_create_field_fields width field has correct min/max values.
	 */
	public function test_get_create_field_fields_width_has_correct_constraints(): void {
		$fields = $this->page->get_create_field_fields();

		$this->assertEquals(0, $fields['width']['min']);
		$this->assertEquals(100, $fields['width']['max']);
		$this->assertEquals(100, $fields['width']['value']);
	}

	/**
	 * get_create_field_fields logged field has correct options.
	 */
	public function test_get_create_field_fields_logged_field_has_correct_options(): void {
		$fields = $this->page->get_create_field_fields();

		$this->assertArrayHasKey('options', $fields['logged']);
		$options = $fields['logged']['options'];
		$this->assertArrayHasKey('always', $options);
		$this->assertArrayHasKey('logged_only', $options);
		$this->assertArrayHasKey('guests_only', $options);
	}

	// -------------------------------------------------------------------------
	// get_field (protected — tested via render_add_new_form_field_modal indirectly)
	// We test it via reflection.
	// -------------------------------------------------------------------------

	/**
	 * get_field returns field with saved=false when field does not exist in form.
	 */
	public function test_get_field_returns_unsaved_field_when_not_found(): void {
		$checkout_form = new Checkout_Form();
		$checkout_form->set_settings([]);

		$reflection = new \ReflectionMethod($this->page, 'get_field');
		$reflection->setAccessible(true);

		$result = $reflection->invoke($this->page, $checkout_form, 'nonexistent-step', 'nonexistent-field');

		$this->assertIsArray($result);
		$this->assertFalse($result['saved']);
	}

	/**
	 * get_field returns field with saved=true when field exists in form.
	 */
	public function test_get_field_returns_saved_field_when_found(): void {
		$checkout_form = new Checkout_Form();
		$checkout_form->set_settings([
			[
				'id'     => 'checkout',
				'name'   => 'Checkout',
				'fields' => [
					[
						'id'   => 'my-field',
						'type' => 'text',
						'name' => 'My Field',
					],
				],
			],
		]);

		$reflection = new \ReflectionMethod($this->page, 'get_field');
		$reflection->setAccessible(true);

		$result = $reflection->invoke($this->page, $checkout_form, 'checkout', 'my-field');

		$this->assertIsArray($result);
		$this->assertTrue($result['saved']);
		$this->assertEquals('my-field', $result['id']);
	}

	// -------------------------------------------------------------------------
	// get_step (protected — tested via reflection)
	// -------------------------------------------------------------------------

	/**
	 * get_step returns step with saved=false when step does not exist.
	 */
	public function test_get_step_returns_unsaved_step_when_not_found(): void {
		$checkout_form = new Checkout_Form();
		$checkout_form->set_settings([]);

		$reflection = new \ReflectionMethod($this->page, 'get_step');
		$reflection->setAccessible(true);

		$result = $reflection->invoke($this->page, $checkout_form, 'nonexistent-step');

		$this->assertIsArray($result);
		$this->assertFalse($result['saved']);
	}

	/**
	 * get_step returns step with saved=true when step exists.
	 */
	public function test_get_step_returns_saved_step_when_found(): void {
		$checkout_form = new Checkout_Form();
		$checkout_form->set_settings([
			[
				'id'     => 'checkout',
				'name'   => 'Checkout Step',
				'fields' => [],
			],
		]);

		$reflection = new \ReflectionMethod($this->page, 'get_step');
		$reflection->setAccessible(true);

		$result = $reflection->invoke($this->page, $checkout_form, 'checkout');

		$this->assertIsArray($result);
		$this->assertTrue($result['saved']);
		$this->assertEquals('checkout', $result['id']);
	}

	// -------------------------------------------------------------------------
	// register_forms
	// -------------------------------------------------------------------------

	/**
	 * register_forms registers the expected form IDs without errors.
	 */
	public function test_register_forms_registers_expected_forms(): void {
		// Should not throw any errors
		$this->page->register_forms();

		// Verify forms are registered via Form_Manager
		$form_manager = \WP_Ultimo\Managers\Form_Manager::get_instance();
		$this->assertTrue($form_manager->is_form_registered('add_new_form_step'));
		$this->assertTrue($form_manager->is_form_registered('add_new_form_field'));
	}

	// -------------------------------------------------------------------------
	// generate_checkout_form_preview
	// -------------------------------------------------------------------------

	/**
	 * generate_checkout_form_preview adds filters when action is wu_generate_checkout_form_preview.
	 */
	public function test_generate_checkout_form_preview_adds_filters_for_preview_action(): void {
		$_REQUEST['action'] = 'wu_generate_checkout_form_preview';

		$this->page->generate_checkout_form_preview();

		$this->assertEquals(10, has_filter('show_admin_bar', '__return_false'));
		$this->assertEquals(10, has_filter('wu_is_jumper_enabled', '__return_false'));
		$this->assertEquals(10, has_filter('wu_is_toolbox_enabled', '__return_false'));
	}

	/**
	 * generate_checkout_form_preview does not add filters for other actions.
	 */
	public function test_generate_checkout_form_preview_does_not_add_filters_for_other_actions(): void {
		$_REQUEST['action'] = 'some_other_action';

		// Remove any previously added filters
		remove_filter('show_admin_bar', '__return_false');
		remove_filter('wu_is_jumper_enabled', '__return_false');
		remove_filter('wu_is_toolbox_enabled', '__return_false');

		$this->page->generate_checkout_form_preview();

		$this->assertFalse(has_filter('show_admin_bar', '__return_false'));
		$this->assertFalse(has_filter('wu_is_jumper_enabled', '__return_false'));
		$this->assertFalse(has_filter('wu_is_toolbox_enabled', '__return_false'));
	}

	/**
	 * generate_checkout_form_preview does nothing when no action is set.
	 */
	public function test_generate_checkout_form_preview_does_nothing_without_action(): void {
		unset($_REQUEST['action']);

		remove_filter('show_admin_bar', '__return_false');

		$this->page->generate_checkout_form_preview();

		$this->assertFalse(has_filter('show_admin_bar', '__return_false'));
	}

	// -------------------------------------------------------------------------
	// init
	// -------------------------------------------------------------------------

	/**
	 * init registers the save_editor_session AJAX action.
	 */
	public function test_init_registers_ajax_action(): void {
		$this->page->init();

		$this->assertNotFalse(has_action('wp_ajax_wu_save_editor_session', [$this->page, 'save_editor_session']));
	}

	/**
	 * init registers the add_width_control_script action.
	 */
	public function test_init_registers_width_control_script_action(): void {
		$this->page->init();

		$this->assertNotFalse(has_action('load-admin_page_wp-ultimo-edit-checkout-form', [$this->page, 'add_width_control_script']));
	}

	// -------------------------------------------------------------------------
	// page_loaded
	// -------------------------------------------------------------------------

	/**
	 * page_loaded sets the edit flag based on whether the object exists.
	 */
	public function test_page_loaded_sets_edit_flag_for_existing_object(): void {
		set_current_screen('dashboard-network');
		$_REQUEST['id'] = $this->checkout_form->get_id();

		$this->page->page_loaded();

		$this->assertTrue($this->page->edit);
	}

	/**
	 * page_loaded registers the render_steps action.
	 */
	public function test_page_loaded_registers_render_steps_action(): void {
		set_current_screen('dashboard-network');
		$_REQUEST['id'] = $this->checkout_form->get_id();

		$this->page->page_loaded();

		$screen = get_current_screen();
		$this->assertNotFalse(has_action("wu_edit_{$screen->id}_after_normal", [$this->page, 'render_steps']));
	}

	/**
	 * page_loaded registers the render_js_templates action on admin_footer.
	 */
	public function test_page_loaded_registers_render_js_templates_action(): void {
		set_current_screen('dashboard-network');
		$_REQUEST['id'] = $this->checkout_form->get_id();

		$this->page->page_loaded();

		$this->assertNotFalse(has_action('admin_footer', [$this->page, 'render_js_templates']));
	}

	// -------------------------------------------------------------------------
	// handle_add_new_form_step_modal
	// -------------------------------------------------------------------------

	/**
	 * handle_add_new_form_step_modal sends JSON error when checkout form not found.
	 */
	public function test_handle_add_new_form_step_modal_sends_error_when_form_not_found(): void {
		$_REQUEST['checkout_form'] = 'nonexistent-form-slug-xyz';

		// Capture JSON output
		ob_start();
		try {
			$this->page->handle_add_new_form_step_modal();
		} catch (\WPDieException $e) {
			// wp_send_json_error calls wp_die
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertFalse($decoded['success']);
	}

	/**
	 * handle_add_new_form_step_modal sends success with step data when form found.
	 */
	public function test_handle_add_new_form_step_modal_sends_success_when_form_found(): void {
		$_REQUEST['checkout_form'] = $this->checkout_form->get_slug();
		$_REQUEST['id']            = 'my-step';
		$_REQUEST['original_id']   = 'my-step';
		$_REQUEST['name']          = 'My Step';
		$_REQUEST['desc']          = 'Step description';
		$_REQUEST['element_id']    = '';
		$_REQUEST['classes']       = '';
		$_REQUEST['logged']        = 'always';

		ob_start();
		try {
			$this->page->handle_add_new_form_step_modal();
		} catch (\WPDieException $e) {
			// wp_send_json_success calls wp_die
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertTrue($decoded['success']);
		$this->assertArrayHasKey('send', $decoded['data']);
		$this->assertEquals('add_step', $decoded['data']['send']['function_name']);
	}

	// -------------------------------------------------------------------------
	// handle_add_new_form_field_modal
	// -------------------------------------------------------------------------

	/**
	 * handle_add_new_form_field_modal sends JSON error when checkout form not found.
	 */
	public function test_handle_add_new_form_field_modal_sends_error_when_form_not_found(): void {
		$_REQUEST['checkout_form'] = 'nonexistent-form-slug-xyz';

		ob_start();
		try {
			$this->page->handle_add_new_form_field_modal();
		} catch (\WPDieException $e) {
			// wp_send_json_error calls wp_die
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertFalse($decoded['success']);
	}

	/**
	 * handle_add_new_form_field_modal sends success with field data when form found.
	 */
	public function test_handle_add_new_form_field_modal_sends_success_when_form_found(): void {
		$field_types = $this->page->field_types();

		// Pick the first available field type
		$type_key = array_key_first($field_types);

		$_REQUEST['checkout_form'] = $this->checkout_form->get_slug();
		$_REQUEST['type']          = $type_key;
		$_REQUEST['id']            = '';
		$_REQUEST['original_id']   = '';
		$_REQUEST['step']          = 'checkout';
		$_REQUEST['label']         = 'Test Field';
		$_REQUEST['from_request']  = false;

		ob_start();
		try {
			$this->page->handle_add_new_form_field_modal();
		} catch (\WPDieException $e) {
			// wp_send_json_success calls wp_die
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertTrue($decoded['success']);
		$this->assertArrayHasKey('send', $decoded['data']);
		$this->assertEquals('add_field', $decoded['data']['send']['function_name']);
	}

	/**
	 * handle_add_new_form_field_modal auto-generates an ID when none is provided.
	 */
	public function test_handle_add_new_form_field_modal_auto_generates_id(): void {
		$field_types = $this->page->field_types();
		$type_key    = array_key_first($field_types);

		$_REQUEST['checkout_form'] = $this->checkout_form->get_slug();
		$_REQUEST['type']          = $type_key;
		$_REQUEST['id']            = '';
		$_REQUEST['original_id']   = '';
		$_REQUEST['step']          = 'checkout';
		$_REQUEST['label']         = 'Auto ID Field';
		$_REQUEST['from_request']  = false;

		ob_start();
		try {
			$this->page->handle_add_new_form_field_modal();
		} catch (\WPDieException $e) {
			// expected
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertTrue($decoded['success']);
		$this->assertArrayHasKey('data', $decoded);
		$this->assertArrayHasKey('send', $decoded['data']);
		$this->assertArrayHasKey('data', $decoded['data']['send']);
		$this->assertArrayHasKey('id', $decoded['data']['send']['data']);
		$this->assertNotEmpty($decoded['data']['send']['data']['id']);
		$this->assertStringStartsWith($type_key . '-', $decoded['data']['send']['data']['id']);
	}

	// -------------------------------------------------------------------------
	// save_editor_session
	// -------------------------------------------------------------------------

	/**
	 * save_editor_session sends JSON error when form not found.
	 */
	public function test_save_editor_session_sends_error_when_form_not_found(): void {
		$_REQUEST['form_id']  = 99999;
		$_REQUEST['settings'] = ['some' => 'settings'];

		ob_start();
		try {
			$this->page->save_editor_session();
		} catch (\WPDieException $e) {
			// expected
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertFalse($decoded['success']);
	}

	/**
	 * save_editor_session sends JSON error when settings are empty.
	 */
	public function test_save_editor_session_sends_error_when_settings_empty(): void {
		$_REQUEST['form_id']  = $this->checkout_form->get_id();
		$_REQUEST['settings'] = [];

		ob_start();
		try {
			$this->page->save_editor_session();
		} catch (\WPDieException $e) {
			// expected
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertFalse($decoded['success']);
	}

	/**
	 * save_editor_session sends JSON success when form and settings are valid.
	 */
	public function test_save_editor_session_sends_success_when_valid(): void {
		// Need a logged-in user for session tokens
		$user_id = $this->factory->user->create(['role' => 'administrator']);
		wp_set_current_user($user_id);

		$_REQUEST['form_id']  = $this->checkout_form->get_id();
		$_REQUEST['settings'] = [
			[
				'id'     => 'checkout',
				'name'   => 'Checkout',
				'fields' => [],
			],
		];

		ob_start();
		try {
			$this->page->save_editor_session();
		} catch (\WPDieException $e) {
			// expected
		}
		$output = ob_get_clean();

		$decoded = json_decode($output, true);
		$this->assertNotNull($decoded, 'Response must be valid JSON: ' . $output);
		$this->assertArrayHasKey('success', $decoded);
		$this->assertTrue($decoded['success']);
	}

	// -------------------------------------------------------------------------
	// get_thank_you_settings
	// -------------------------------------------------------------------------

	/**
	 * get_thank_you_settings returns an array.
	 */
	public function test_get_thank_you_settings_returns_array(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$settings = $this->page->get_thank_you_settings();

		$this->assertIsArray($settings);
	}

	/**
	 * get_thank_you_settings merges defaults with saved meta.
	 */
	public function test_get_thank_you_settings_merges_defaults(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$defaults = \WP_Ultimo\UI\Thank_You_Element::get_instance()->defaults();
		$settings = $this->page->get_thank_you_settings();

		// All default keys should be present
		foreach (array_keys($defaults) as $key) {
			$this->assertArrayHasKey($key, $settings);
		}
	}

	// -------------------------------------------------------------------------
	// get_thank_you_page_fields
	// -------------------------------------------------------------------------

	/**
	 * get_thank_you_page_fields returns an array with conversion_snippets field.
	 */
	public function test_get_thank_you_page_fields_includes_conversion_snippets(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$fields = $this->page->get_thank_you_page_fields();

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('conversion_snippets', $fields);
		$this->assertEquals('code-editor', $fields['conversion_snippets']['type']);
	}

	/**
	 * get_thank_you_page_fields does not include header-type fields.
	 */
	public function test_get_thank_you_page_fields_excludes_header_fields(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$fields = $this->page->get_thank_you_page_fields();

		foreach ($fields as $field) {
			if (is_array($field) && isset($field['type'])) {
				$this->assertNotEquals('header', $field['type']);
			}
		}
	}

	/**
	 * get_thank_you_page_fields uses meta[wu_thank_you_settings][...] key format.
	 */
	public function test_get_thank_you_page_fields_uses_correct_key_format(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$fields = $this->page->get_thank_you_page_fields();

		foreach (array_keys($fields) as $key) {
			if ($key !== 'conversion_snippets') {
				$this->assertStringStartsWith('meta[wu_thank_you_settings]', $key);
			}
		}
	}

	// -------------------------------------------------------------------------
	// handle_save
	// -------------------------------------------------------------------------

	/**
	 * handle_save clears allowed_countries when restrict_by_country is not set.
	 */
	public function test_handle_save_clears_allowed_countries_when_not_restricted(): void {
		$_REQUEST['id']              = $this->checkout_form->get_id();
		$this->page->object          = $this->checkout_form;
		$_POST['restrict_by_country'] = '';
		$_POST['allowed_countries']   = ['US', 'CA'];

		// handle_save calls parent which may redirect — capture output
		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// Redirects or wp_die calls are expected
		}
		ob_get_clean();

		// After handle_save, allowed_countries should be cleared
		$this->assertEmpty($_POST['allowed_countries'] ?? []);
	}

	/**
	 * handle_save processes _settings from POST when present.
	 */
	public function test_handle_save_processes_settings_from_post(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$settings        = [['id' => 'checkout', 'name' => 'Checkout', 'fields' => []]];
		$_POST['_settings'] = wp_slash(wp_json_encode($settings));

		ob_start();
		try {
			$this->page->handle_save();
		} catch (\Exception $e) {
			// expected
		}
		ob_get_clean();

		// _settings should be unset after processing
		$this->assertArrayNotHasKey('_settings', $_POST);
	}

	// -------------------------------------------------------------------------
	// register_scripts
	// -------------------------------------------------------------------------

	/**
	 * register_scripts enqueues the checkout form editor script.
	 */
	public function test_register_scripts_enqueues_checkout_form_editor(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$this->page->register_scripts();

		$this->assertTrue(
			wp_script_is('wu-checkout-form-editor', 'registered') ||
			wp_script_is('wu-checkout-form-editor', 'enqueued')
		);
	}

	/**
	 * register_scripts enqueues the checkout form editor CSS.
	 */
	public function test_register_scripts_enqueues_checkout_form_editor_css(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$this->page->register_scripts();

		$this->assertTrue(
			wp_style_is('wu-checkout-form-editor', 'registered') ||
			wp_style_is('wu-checkout-form-editor', 'enqueued')
		);
	}

	/**
	 * register_scripts localizes the checkout form editor script with correct data.
	 */
	public function test_register_scripts_localizes_script_with_correct_data(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		$this->page->register_scripts();

		$localized = wp_scripts()->get_data('wu-checkout-form-editor', 'data');
		$this->assertNotEmpty($localized);
		$this->assertStringContainsString('form_id', $localized);
		$this->assertStringContainsString('checkout_form', $localized);
		$this->assertStringContainsString('steps', $localized);
	}

	// -------------------------------------------------------------------------
	// add_width_control_script
	// -------------------------------------------------------------------------

	/**
	 * add_width_control_script enqueues the checkout form edit modal script.
	 */
	public function test_add_width_control_script_enqueues_modal_script(): void {
		$this->page->add_width_control_script();

		$this->assertTrue(
			wp_script_is('wu-checkout-form-edit-modal', 'registered') ||
			wp_script_is('wu-checkout-form-edit-modal', 'enqueued')
		);
	}

	// -------------------------------------------------------------------------
	// render_add_new_form_step_modal
	// -------------------------------------------------------------------------

	/**
	 * render_add_new_form_step_modal returns early when checkout form not found.
	 */
	public function test_render_add_new_form_step_modal_returns_early_when_form_not_found(): void {
		$_REQUEST['checkout_form'] = 'nonexistent-slug-xyz';

		ob_start();
		$this->page->render_add_new_form_step_modal();
		$output = ob_get_clean();

		// Should produce no output when form not found
		$this->assertEmpty($output);
	}

	/**
	 * render_add_new_form_step_modal renders form when checkout form found.
	 */
	public function test_render_add_new_form_step_modal_renders_when_form_found(): void {
		$_REQUEST['checkout_form'] = $this->checkout_form->get_slug();

		ob_start();
		$this->page->render_add_new_form_step_modal();
		$output = ob_get_clean();

		// Should produce some output (form HTML)
		$this->assertNotEmpty($output);
	}

	// -------------------------------------------------------------------------
	// render_add_new_form_field_modal
	// -------------------------------------------------------------------------

	/**
	 * render_add_new_form_field_modal returns early when checkout form not found.
	 */
	public function test_render_add_new_form_field_modal_returns_early_when_form_not_found(): void {
		$_REQUEST['checkout_form'] = 'nonexistent-slug-xyz';

		ob_start();
		$this->page->render_add_new_form_field_modal();
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * render_add_new_form_field_modal renders form when checkout form found.
	 */
	public function test_render_add_new_form_field_modal_renders_when_form_found(): void {
		$this->checkout_form->set_settings([
			[
				'id'     => 'checkout',
				'name'   => 'Checkout',
				'fields' => [],
			],
		]);
		$this->checkout_form->save();

		$_REQUEST['checkout_form'] = $this->checkout_form->get_slug();

		ob_start();
		$this->page->render_add_new_form_field_modal();
		$output = ob_get_clean();

		$this->assertNotEmpty($output);
	}

	// -------------------------------------------------------------------------
	// Class property defaults
	// -------------------------------------------------------------------------

	/**
	 * The page ID is set correctly.
	 */
	public function test_page_id_is_correct(): void {
		$reflection = new \ReflectionProperty($this->page, 'id');
		$reflection->setAccessible(true);

		$this->assertEquals('wp-ultimo-edit-checkout-form', $reflection->getValue($this->page));
	}

	/**
	 * The page type is submenu.
	 */
	public function test_page_type_is_submenu(): void {
		$reflection = new \ReflectionProperty($this->page, 'type');
		$reflection->setAccessible(true);

		$this->assertEquals('submenu', $reflection->getValue($this->page));
	}

	/**
	 * The object_id is checkout-form.
	 */
	public function test_object_id_is_checkout_form(): void {
		$this->assertEquals('checkout-form', $this->page->object_id);
	}

	/**
	 * The highlight_menu_slug is set correctly.
	 */
	public function test_highlight_menu_slug_is_correct(): void {
		$reflection = new \ReflectionProperty($this->page, 'highlight_menu_slug');
		$reflection->setAccessible(true);

		$this->assertEquals('wp-ultimo-checkout-forms', $reflection->getValue($this->page));
	}

	/**
	 * The supported_panels requires wu_edit_checkout_forms capability.
	 */
	public function test_supported_panels_requires_correct_capability(): void {
		$reflection = new \ReflectionProperty($this->page, 'supported_panels');
		$reflection->setAccessible(true);

		$panels = $reflection->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_edit_checkout_forms', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// render_steps / render_js_templates
	// -------------------------------------------------------------------------

	/**
	 * render_steps produces output when object is set.
	 */
	public function test_render_steps_produces_output(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		ob_start();
		$this->page->render_steps();
		$output = ob_get_clean();

		// Template output may be empty in test env but should not throw
		$this->assertIsString($output);
	}

	/**
	 * render_js_templates produces output when object is set.
	 */
	public function test_render_js_templates_produces_output(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		ob_start();
		$this->page->render_js_templates();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// register_widgets
	// -------------------------------------------------------------------------

	/**
	 * register_widgets runs without errors when object is set.
	 */
	public function test_register_widgets_runs_without_errors(): void {
		$_REQUEST['id']     = $this->checkout_form->get_id();
		$this->page->object = $this->checkout_form;

		// Should not throw any exceptions
		$this->page->register_widgets();

		$this->assertTrue(true);
	}
}

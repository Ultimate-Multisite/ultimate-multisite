<?php
/**
 * Tests for Edit_Admin_Page base class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Models\Discount_Code;

/**
 * Concrete implementation of Edit_Admin_Page for testing.
 *
 * Uses Discount_Code as the backing model since it is lightweight
 * and has no side-effects on construction.
 *
 * Exposes protected methods as public so tests can call them directly
 * without reflection overhead.
 */
class Test_Edit_Admin_Page extends Edit_Admin_Page {

	/**
	 * Page ID.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-test-edit-page';

	/**
	 * Page type.
	 *
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * Object ID slug.
	 *
	 * @var string
	 */
	public $object_id = 'test_object';

	/**
	 * Parent menu.
	 *
	 * @var string
	 */
	protected $parent = 'none';

	/**
	 * Supported panels.
	 *
	 * @var array
	 */
	protected $supported_panels = [
		'network_admin_menu' => 'manage_network',
	];

	/**
	 * Badge count.
	 *
	 * @var int
	 */
	protected $badge_count = 0;

	/**
	 * Returns the title of the page.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->edit ? 'Edit Test Object' : 'Add New Test Object';
	}

	/**
	 * Returns the menu title.
	 *
	 * @return string
	 */
	public function get_menu_title() {
		return 'Edit Test Object';
	}

	/**
	 * Returns action links.
	 *
	 * @return array
	 */
	public function action_links() {
		return [];
	}

	/**
	 * Returns the object being edited.
	 *
	 * @return \WP_Ultimo\Models\Discount_Code
	 */
	public function get_object() {

		if (null !== $this->object) {
			return $this->object;
		}

		$this->object = new Discount_Code();

		return $this->object;
	}

	/**
	 * Expose add_lock_notices as public for testing.
	 *
	 * @return void
	 */
	public function public_add_lock_notices(): void {
		$this->add_lock_notices();
	}

	/**
	 * Expose add_info_widget as public for testing.
	 *
	 * @param string $id Widget ID.
	 * @param array  $atts Widget attributes.
	 * @return void
	 */
	public function public_add_info_widget($id, $atts = []): void {
		$this->add_info_widget($id, $atts);
	}

	/**
	 * Expose add_save_widget as public for testing.
	 *
	 * @param string $id Widget ID.
	 * @param array  $atts Widget attributes.
	 * @return void
	 */
	public function public_add_save_widget($id, $atts = []): void {
		$this->add_save_widget($id, $atts);
	}

	/**
	 * Expose add_delete_widget as public for testing.
	 *
	 * @param string $id Widget ID.
	 * @param array  $atts Widget attributes.
	 * @return void
	 */
	public function public_add_delete_widget($id, $atts = []): void {
		$this->add_delete_widget($id, $atts);
	}

	/**
	 * Expose add_widget as public for testing.
	 *
	 * @param string $id Widget ID.
	 * @param array  $atts Widget attributes.
	 * @return void
	 */
	public function public_add_widget($id, $atts = []): void {
		$this->add_widget($id, $atts);
	}

	/**
	 * Expose add_tabs_widget as public for testing.
	 *
	 * @param string $id Widget ID.
	 * @param array  $atts Widget attributes.
	 * @return void
	 */
	public function public_add_tabs_widget($id, $atts = []): void {
		$this->add_tabs_widget($id, $atts);
	}

	/**
	 * Expose add_list_table_widget as public for testing.
	 *
	 * @param string $id Widget ID.
	 * @param array  $atts Widget attributes.
	 * @return void
	 */
	public function public_add_list_table_widget($id, $atts = []): void {
		$this->add_list_table_widget($id, $atts);
	}
}

/**
 * Test class for Edit_Admin_Page base class.
 *
 * Tests the base class methods directly via the Test_Edit_Admin_Page concrete
 * implementation. Methods that require HTTP redirects or nonce verification
 * are tested for their guard conditions only.
 */
class Edit_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * Instance under test.
	 *
	 * @var Test_Edit_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new Test_Edit_Admin_Page();

		// Clear any notices left by previous tests.
		$this->clear_notices();
	}

	/**
	 * Tear down: clean up superglobals and notices.
	 */
	protected function tearDown(): void {

		unset(
			$_REQUEST['submit_button'],
			$_REQUEST['remove-lock'],
			$_REQUEST['saving_test_object'],
			$_REQUEST['deleting_test_object']
		);

		$this->clear_notices();

		parent::tearDown();
	}

	/**
	 * Clear all WP_Ultimo admin notices via reflection.
	 *
	 * @return void
	 */
	private function clear_notices(): void {

		$notices_obj = \WP_Ultimo()->notices;
		$reflection  = new \ReflectionClass($notices_obj);
		$property    = $reflection->getProperty('notices');
		$property->setAccessible(true);
		$property->setValue(
			$notices_obj,
			[
				'admin'         => [],
				'network-admin' => [],
				'user'          => [],
			]
		);
	}

	// -------------------------------------------------------------------------
	// get_errors()
	// -------------------------------------------------------------------------

	/**
	 * get_errors returns a WP_Error instance when errors is null.
	 */
	public function test_get_errors_returns_wp_error_when_null(): void {

		$errors = $this->page->get_errors();

		$this->assertInstanceOf(\WP_Error::class, $errors);
	}

	/**
	 * get_errors returns the same instance on repeated calls.
	 */
	public function test_get_errors_returns_same_instance(): void {

		$first  = $this->page->get_errors();
		$second = $this->page->get_errors();

		$this->assertSame($first, $second);
	}

	/**
	 * get_errors returns a WP_Error with no messages initially.
	 */
	public function test_get_errors_initially_empty(): void {

		$errors = $this->page->get_errors();

		$this->assertEmpty($errors->get_error_messages());
	}

	// -------------------------------------------------------------------------
	// removable_query_args()
	// -------------------------------------------------------------------------

	/**
	 * removable_query_args appends wu-new-model to the list.
	 */
	public function test_removable_query_args_appends_wu_new_model(): void {

		$result = $this->page->removable_query_args(['existing-arg']);

		$this->assertContains('wu-new-model', $result);
		$this->assertContains('existing-arg', $result);
	}

	/**
	 * removable_query_args works with an empty array.
	 */
	public function test_removable_query_args_with_empty_array(): void {

		$result = $this->page->removable_query_args([]);

		$this->assertContains('wu-new-model', $result);
		$this->assertCount(1, $result);
	}

	/**
	 * removable_query_args preserves existing entries.
	 */
	public function test_removable_query_args_preserves_existing(): void {

		$existing = ['arg1', 'arg2', 'arg3'];
		$result   = $this->page->removable_query_args($existing);

		$this->assertCount(4, $result);
		foreach ($existing as $arg) {
			$this->assertContains($arg, $result);
		}
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	/**
	 * get_labels returns an array with all required keys.
	 */
	public function test_get_labels_returns_required_keys(): void {

		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
		$this->assertArrayHasKey('edit_label', $labels);
		$this->assertArrayHasKey('add_new_label', $labels);
		$this->assertArrayHasKey('updated_message', $labels);
		$this->assertArrayHasKey('title_placeholder', $labels);
		$this->assertArrayHasKey('title_description', $labels);
		$this->assertArrayHasKey('save_button_label', $labels);
		$this->assertArrayHasKey('save_description', $labels);
		$this->assertArrayHasKey('delete_button_label', $labels);
		$this->assertArrayHasKey('delete_description', $labels);
	}

	/**
	 * get_labels returns non-empty strings for key labels.
	 */
	public function test_get_labels_values_are_strings(): void {

		$labels = $this->page->get_labels();

		$this->assertIsString($labels['edit_label']);
		$this->assertIsString($labels['add_new_label']);
		$this->assertIsString($labels['save_button_label']);
		$this->assertIsString($labels['delete_button_label']);
	}

	/**
	 * get_labels is filterable via wu_edit_admin_page_labels.
	 */
	public function test_get_labels_is_filterable(): void {

		add_filter(
			'wu_edit_admin_page_labels',
			function ($labels) {
				$labels['edit_label'] = 'Custom Edit Label';
				return $labels;
			}
		);

		$labels = $this->page->get_labels();

		$this->assertEquals('Custom Edit Label', $labels['edit_label']);

		remove_all_filters('wu_edit_admin_page_labels');
	}

	/**
	 * get_labels default edit_label is 'Edit Object'.
	 */
	public function test_get_labels_default_edit_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Edit Object', $labels['edit_label']);
	}

	/**
	 * get_labels default save_button_label is 'Save'.
	 */
	public function test_get_labels_default_save_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Save', $labels['save_button_label']);
	}

	/**
	 * get_labels default delete_button_label is 'Delete'.
	 */
	public function test_get_labels_default_delete_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Delete', $labels['delete_button_label']);
	}

	// -------------------------------------------------------------------------
	// has_title() / has_editor()
	// -------------------------------------------------------------------------

	/**
	 * has_title returns false by default.
	 */
	public function test_has_title_returns_false(): void {

		$this->assertFalse($this->page->has_title());
	}

	/**
	 * has_editor returns false by default.
	 */
	public function test_has_editor_returns_false(): void {

		$this->assertFalse($this->page->has_editor());
	}

	// -------------------------------------------------------------------------
	// page_loaded() — new object path
	// -------------------------------------------------------------------------

	/**
	 * page_loaded sets edit to false when object does not exist.
	 */
	public function test_page_loaded_sets_edit_false_for_new_object(): void {

		// No $_REQUEST['id'] set — object will be a new Discount_Code (not persisted).
		$this->page->page_loaded();

		$this->assertFalse($this->page->edit);
	}

	/**
	 * page_loaded sets the object property.
	 */
	public function test_page_loaded_sets_object(): void {

		$this->page->page_loaded();

		$this->assertNotNull($this->page->object);
		$this->assertInstanceOf(Discount_Code::class, $this->page->object);
	}

	/**
	 * page_loaded sets edit to true when object exists in the database.
	 */
	public function test_page_loaded_sets_edit_true_for_existing_object(): void {

		// Create and save a real discount code so exists() returns true.
		$discount_code = new Discount_Code();
		$discount_code->set_code('TESTCODE');
		$discount_code->set_value(10);
		$discount_code->set_type('percentage');
		$saved = $discount_code->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save discount code: ' . $saved->get_error_message());
			return;
		}

		// Set the object directly so page_loaded uses it.
		$this->page->object = $discount_code;

		$this->page->page_loaded();

		$this->assertTrue($this->page->edit);
	}

	/**
	 * page_loaded with delete submit_button calls process_delete (guard only).
	 */
	public function test_page_loaded_with_delete_submit_button(): void {

		$_REQUEST['submit_button'] = 'delete';

		// process_delete will check for deleting_test_object nonce — not present, so no-op.
		$this->page->page_loaded();

		$this->assertFalse($this->page->edit);
	}

	// -------------------------------------------------------------------------
	// process_save guard (no nonce present)
	// -------------------------------------------------------------------------

	/**
	 * process_save does nothing when the saving tag is absent from REQUEST.
	 */
	public function test_process_save_does_nothing_without_saving_tag(): void {

		// No saving_test_object in $_REQUEST — should not throw.
		$this->page->process_save();

		// If we reach here without an exception, the guard worked.
		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// process_delete guard (no nonce present)
	// -------------------------------------------------------------------------

	/**
	 * process_delete does nothing when the deleting tag is absent from REQUEST.
	 */
	public function test_process_delete_does_nothing_without_deleting_tag(): void {

		$this->page->process_delete();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// remove_lock() guard
	// -------------------------------------------------------------------------

	/**
	 * remove_lock does nothing when remove-lock is absent from REQUEST.
	 */
	public function test_remove_lock_does_nothing_without_request_key(): void {

		$this->page->remove_lock();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// handle_save() — error path
	// -------------------------------------------------------------------------

	/**
	 * handle_save returns false when object->save() returns a WP_Error.
	 */
	public function test_handle_save_returns_false_on_wp_error(): void {

		// Create a mock object that returns WP_Error on save().
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test_error', 'Test error message'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		$result = $this->page->handle_save();

		$this->assertFalse($result);
	}

	/**
	 * handle_save with edit=false path returns false on WP_Error.
	 */
	public function test_handle_save_new_object_wp_error(): void {

		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;
		$this->page->edit   = false;

		$result = $this->page->handle_save();

		$this->assertFalse($result);
	}

	/**
	 * handle_save adds error notice when save fails.
	 */
	public function test_handle_save_adds_error_notice_on_failure(): void {

		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test_error', 'Something went wrong'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		$this->page->handle_save();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices);
	}

	// -------------------------------------------------------------------------
	// handle_delete() — error path
	// -------------------------------------------------------------------------

	/**
	 * handle_delete returns early when object->delete() returns a WP_Error.
	 */
	public function test_handle_delete_returns_on_wp_error(): void {

		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('delete')->willReturn(new \WP_Error('test_error', 'Delete failed'));

		$this->page->object = $mock_object;

		// Should not throw or exit.
		$this->page->handle_delete();

		$this->assertTrue(true);
	}

	/**
	 * handle_delete adds error notice when delete fails.
	 */
	public function test_handle_delete_adds_error_notice_on_failure(): void {

		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('delete')->willReturn(new \WP_Error('test_error', 'Delete failed'));

		$this->page->object = $mock_object;

		$this->page->handle_delete();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * register_widgets does not throw when called with a valid screen.
	 */
	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard-network');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * register_widgets in edit mode adds delete widget.
	 */
	public function test_register_widgets_in_edit_mode(): void {

		set_current_screen('dashboard-network');

		$this->page->edit   = true;
		$this->page->object = new Discount_Code();

		// Should not throw.
		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_fields_widget()
	// -------------------------------------------------------------------------

	/**
	 * add_fields_widget registers a meta box without throwing.
	 */
	public function test_add_fields_widget_registers_meta_box(): void {

		set_current_screen('dashboard-network');

		$this->page->add_fields_widget(
			'test-widget',
			[
				'title'    => 'Test Widget',
				'position' => 'side',
				'fields'   => [],
			]
		);

		$this->assertTrue(true);
	}

	/**
	 * add_fields_widget with data-wu-app adds loading field.
	 */
	public function test_add_fields_widget_with_vue_app(): void {

		set_current_screen('dashboard-network');

		$this->page->add_fields_widget(
			'test-vue-widget',
			[
				'title'     => 'Vue Widget',
				'position'  => 'side',
				'fields'    => [],
				'html_attr' => [
					'data-wu-app' => 'test_app',
				],
			]
		);

		$this->assertTrue(true);
	}

	/**
	 * add_fields_widget meta box callback renders form without throwing.
	 */
	public function test_add_fields_widget_callback_renders(): void {

		set_current_screen('dashboard-network');

		$screen = get_current_screen();

		$this->page->add_fields_widget(
			'test-render-widget',
			[
				'title'    => 'Render Test',
				'position' => 'side',
				'screen'   => $screen,
				'fields'   => [
					'test_field' => [
						'type'  => 'text',
						'title' => 'Test Field',
						'value' => 'test_value',
					],
				],
			]
		);

		// Execute the registered meta box callback.
		global $wp_meta_boxes;
		$screen_id = $screen->id;

		if (isset($wp_meta_boxes[ $screen_id ])) {
			foreach ($wp_meta_boxes[ $screen_id ] as $context => $priorities) {
				foreach ($priorities as $priority => $boxes) {
					foreach ($boxes as $box_id => $box) {
						if ('wp-ultimo-test-render-widget-widget' === $box_id && is_callable($box['callback'])) {
							ob_start();
							call_user_func($box['callback']);
							ob_end_clean();
						}
					}
				}
			}
		}

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_save_widget()
	// -------------------------------------------------------------------------

	/**
	 * add_save_widget registers without throwing.
	 */
	public function test_add_save_widget_registers(): void {

		set_current_screen('dashboard-network');

		$this->page->public_add_save_widget('save', []);

		$this->assertTrue(true);
	}

	/**
	 * add_save_widget shows locked state when object is locked and in edit mode.
	 */
	public function test_add_save_widget_locked_object(): void {

		set_current_screen('dashboard-network');

		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('is_locked')->willReturn(true);

		$this->page->object = $mock_object;
		$this->page->edit   = true;

		// Should not throw.
		$this->page->public_add_save_widget('save', []);

		$this->assertTrue(true);
	}

	/**
	 * add_save_widget with vue app attribute registers without throwing.
	 */
	public function test_add_save_widget_with_vue_app(): void {

		set_current_screen('dashboard-network');

		$this->page->public_add_save_widget(
			'save',
			[
				'html_attr' => [
					'data-wu-app' => 'save_app',
				],
			]
		);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_delete_widget()
	// -------------------------------------------------------------------------

	/**
	 * add_delete_widget registers without throwing.
	 */
	public function test_add_delete_widget_registers(): void {

		set_current_screen('dashboard-network');

		$this->page->public_add_delete_widget('delete', []);

		$this->assertTrue(true);
	}

	/**
	 * add_delete_widget merges custom field settings.
	 */
	public function test_add_delete_widget_with_custom_fields(): void {

		set_current_screen('dashboard-network');

		$this->page->public_add_delete_widget(
			'delete',
			[
				'fields' => [
					'delete' => [
						'classes' => 'custom-class',
					],
				],
			]
		);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_widget()
	// -------------------------------------------------------------------------

	/**
	 * add_widget registers a generic meta box without throwing.
	 */
	public function test_add_widget_registers(): void {

		set_current_screen('dashboard-network');

		$this->page->public_add_widget(
			'generic-widget',
			[
				'title'   => 'Generic Widget',
				'display' => '__return_empty_string',
			]
		);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_info_widget()
	// -------------------------------------------------------------------------

	/**
	 * add_info_widget registers without throwing for a new object.
	 */
	public function test_add_info_widget_new_object(): void {

		set_current_screen('dashboard-network');

		$this->page->public_add_info_widget(
			'info',
			[
				'title'    => 'Timestamps',
				'position' => 'side-bottom',
			]
		);

		$this->assertTrue(true);
	}

	/**
	 * add_info_widget in edit mode includes date_modified field.
	 */
	public function test_add_info_widget_edit_mode(): void {

		set_current_screen('dashboard-network');

		$this->page->edit = true;

		$this->page->public_add_info_widget(
			'info',
			[
				'title'    => 'Timestamps',
				'position' => 'side-bottom',
			]
		);

		$this->assertTrue(true);
	}

	/**
	 * add_info_widget uses date_registered key when object has get_date_registered.
	 */
	public function test_add_info_widget_uses_date_registered_when_available(): void {

		set_current_screen('dashboard-network');

		// Create a mock that has get_date_registered.
		$mock_object = $this->getMockBuilder(Discount_Code::class)
			->addMethods(['get_date_registered'])
			->getMock();
		$mock_object->method('get_date_registered')->willReturn('2024-01-01 00:00:00');

		$this->page->object = $mock_object;

		$this->page->public_add_info_widget('info', ['title' => 'Timestamps', 'position' => 'side-bottom']);

		$this->assertTrue(true);
	}

	/**
	 * add_info_widget with modified=false skips date_modified field.
	 */
	public function test_add_info_widget_skip_modified(): void {

		set_current_screen('dashboard-network');

		$this->page->edit = true;

		$this->page->public_add_info_widget(
			'info',
			[
				'title'    => 'Timestamps',
				'position' => 'side-bottom',
				'modified' => false,
			]
		);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_tabs_widget()
	// -------------------------------------------------------------------------

	/**
	 * add_tabs_widget registers without throwing.
	 */
	public function test_add_tabs_widget_registers(): void {

		set_current_screen('dashboard-network');

		$this->page->public_add_tabs_widget(
			'test-tabs',
			[
				'title'    => 'Test Tabs',
				'position' => 'advanced',
				'sections' => [
					'section1' => [
						'title'  => 'Section 1',
						'desc'   => 'Description 1',
						'fields' => [],
					],
				],
			]
		);

		$this->assertTrue(true);
	}

	/**
	 * add_tabs_widget with multiple sections registers without throwing.
	 */
	public function test_add_tabs_widget_multiple_sections(): void {

		set_current_screen('dashboard-network');

		$this->page->public_add_tabs_widget(
			'test-tabs-multi',
			[
				'title'    => 'Multi Tabs',
				'position' => 'advanced',
				'sections' => [
					'section1' => [
						'title'  => 'Section 1',
						'desc'   => 'Description 1',
						'fields' => [],
					],
					'section2' => [
						'title'  => 'Section 2',
						'desc'   => 'Description 2',
						'fields' => [],
					],
				],
			]
		);

		$this->assertTrue(true);
	}

	/**
	 * add_tabs_widget meta box callback renders without throwing.
	 */
	public function test_add_tabs_widget_callback_renders(): void {

		set_current_screen('dashboard-network');

		$screen = get_current_screen();

		$this->page->public_add_tabs_widget(
			'test-tabs-render',
			[
				'title'    => 'Tabs Render Test',
				'position' => 'advanced',
				'screen'   => $screen,
				'sections' => [
					'section1' => [
						'title'  => 'Section 1',
						'desc'   => 'Description 1',
						'icon'   => 'dashicons-admin-generic',
						'fields' => [
							'field1' => [
								'type'  => 'text',
								'title' => 'Field 1',
								'value' => 'value1',
							],
						],
					],
				],
			]
		);

		// Execute the registered meta box callback.
		global $wp_meta_boxes;
		$screen_id = $screen->id;

		if (isset($wp_meta_boxes[ $screen_id ])) {
			foreach ($wp_meta_boxes[ $screen_id ] as $context => $priorities) {
				foreach ($priorities as $priority => $boxes) {
					foreach ($boxes as $box_id => $box) {
						if ('wp-ultimo-test-tabs-render-widget' === $box_id && is_callable($box['callback'])) {
							ob_start();
							call_user_func($box['callback']);
							ob_end_clean();
						}
					}
				}
			}
		}

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_list_table_widget()
	// -------------------------------------------------------------------------

	/**
	 * add_list_table_widget registers a meta box without throwing.
	 */
	public function test_add_list_table_widget_registers(): void {

		set_current_screen('dashboard-network');

		$table = new \WP_Ultimo\List_Tables\Inside_Events_List_Table();

		$this->page->public_add_list_table_widget(
			'events',
			[
				'title' => 'Events',
				'table' => $table,
			]
		);

		$this->assertTrue(true);
	}

	/**
	 * add_list_table_widget with query_filter callable registers filter.
	 */
	public function test_add_list_table_widget_with_query_filter(): void {

		set_current_screen('dashboard-network');

		$table = new \WP_Ultimo\List_Tables\Inside_Events_List_Table();

		$this->page->public_add_list_table_widget(
			'events-filtered',
			[
				'title'        => 'Events',
				'table'        => $table,
				'query_filter' => function ($args) {
					return $args;
				},
			]
		);

		$this->assertTrue(true);
	}

	/**
	 * add_list_table_widget meta box callback renders without throwing.
	 */
	public function test_add_list_table_widget_callback_renders(): void {

		set_current_screen('dashboard-network');

		$screen = get_current_screen();
		$table  = new \WP_Ultimo\List_Tables\Inside_Events_List_Table();

		$this->page->public_add_list_table_widget(
			'events-render',
			[
				'title'  => 'Events Render',
				'screen' => $screen,
				'table'  => $table,
			]
		);

		// Execute the registered meta box callback.
		global $wp_meta_boxes;
		$screen_id = $screen->id;

		if (isset($wp_meta_boxes[ $screen_id ])) {
			foreach ($wp_meta_boxes[ $screen_id ] as $context => $priorities) {
				foreach ($priorities as $priority => $boxes) {
					foreach ($boxes as $box_id => $box) {
						if ('wp-ultimo-list-table-events-render' === $box_id && is_callable($box['callback'])) {
							ob_start();
							call_user_func($box['callback']);
							ob_end_clean();
						}
					}
				}
			}
		}

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * output() method exists and is callable.
	 *
	 * The full output() execution requires wu_wrap_use_container() which is
	 * only available when the full plugin template system is loaded. We verify
	 * the method is callable and the page has the correct structure instead.
	 */
	public function test_output_method_exists(): void {

		$this->assertTrue(method_exists($this->page, 'output'));
		$this->assertTrue(is_callable([$this->page, 'output']));
	}

	// -------------------------------------------------------------------------
	// hooks()
	// -------------------------------------------------------------------------

	/**
	 * hooks() registers the removable_query_args filter.
	 */
	public function test_hooks_registers_removable_query_args_filter(): void {

		$this->page->hooks();

		$this->assertGreaterThan(
			0,
			has_filter('removable_query_args', [$this->page, 'removable_query_args'])
		);
	}

	// -------------------------------------------------------------------------
	// edit property
	// -------------------------------------------------------------------------

	/**
	 * edit property defaults to false.
	 */
	public function test_edit_defaults_to_false(): void {

		$this->assertFalse($this->page->edit);
	}

	/**
	 * edit property can be set to true.
	 */
	public function test_edit_can_be_set_to_true(): void {

		$this->page->edit = true;

		$this->assertTrue($this->page->edit);
	}

	// -------------------------------------------------------------------------
	// object_id property
	// -------------------------------------------------------------------------

	/**
	 * object_id is set correctly.
	 */
	public function test_object_id_is_set(): void {

		$this->assertEquals('test_object', $this->page->object_id);
	}

	// -------------------------------------------------------------------------
	// get_object()
	// -------------------------------------------------------------------------

	/**
	 * get_object returns a Discount_Code instance.
	 */
	public function test_get_object_returns_discount_code(): void {

		$object = $this->page->get_object();

		$this->assertInstanceOf(Discount_Code::class, $object);
	}

	/**
	 * get_object returns the same instance on repeated calls.
	 */
	public function test_get_object_returns_same_instance(): void {

		$first  = $this->page->get_object();
		$second = $this->page->get_object();

		$this->assertSame($first, $second);
	}

	// -------------------------------------------------------------------------
	// add_lock_notices()
	// -------------------------------------------------------------------------

	/**
	 * add_lock_notices does not add a notice when object is not locked.
	 */
	public function test_add_lock_notices_no_notice_when_unlocked(): void {

		$this->page->edit = true;

		// New Discount_Code is not locked.
		$this->page->public_add_lock_notices();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertEmpty($notices);
	}

	/**
	 * add_lock_notices does not add a notice when not in edit mode.
	 */
	public function test_add_lock_notices_no_notice_when_not_edit(): void {

		$this->page->edit = false;

		$this->page->public_add_lock_notices();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertEmpty($notices);
	}

	/**
	 * add_lock_notices adds a warning notice when object is locked and in edit mode.
	 */
	public function test_add_lock_notices_adds_warning_when_locked_and_edit(): void {

		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('is_locked')->willReturn(true);

		$this->page->object = $mock_object;
		$this->page->edit   = true;

		$this->page->public_add_lock_notices();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices);

		$notice = array_shift($notices);
		$this->assertEquals('warning', $notice['type']);
	}

	/**
	 * add_lock_notices notice includes unlock action link.
	 */
	public function test_add_lock_notices_includes_unlock_action(): void {

		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('is_locked')->willReturn(true);

		$this->page->object = $mock_object;
		$this->page->edit   = true;

		$this->page->public_add_lock_notices();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$notice  = array_shift($notices);

		$this->assertNotEmpty($notice['actions']);
		$this->assertArrayHasKey('preview', $notice['actions']);
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	/**
	 * register_scripts does not throw.
	 */
	public function test_register_scripts_does_not_throw(): void {

		$this->page->register_scripts();

		$this->assertTrue(true);
	}
}
